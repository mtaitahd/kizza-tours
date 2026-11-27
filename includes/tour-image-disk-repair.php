<?php
require_once __DIR__ . '/tour-image-repair.php';

/** Repair public-read permissions and validate media already on this server. Never deletes original photos. */
class TourImageDiskRepair
{
    private $root;
    private const EXTENSIONS = ['webp','jpg','jpeg','png','gif','avif','bmp','svg','tif','tiff','heic','heif'];
    public function __construct($root) { $this->root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/') . '/'; }
    private function inside($path)
    {
        $real = realpath($path);
        return $real && str_starts_with(str_replace('\\', '/', $real), $this->root . 'uploads/') && !is_link($path);
    }
    public function publicPermissions($path, $directory = false)
    {
        if (!$this->inside($path) && str_replace('\\', '/', $path) !== $this->root . 'uploads') throw new RuntimeException('Permission target is outside uploaded media.');
        if (is_link($path)) throw new RuntimeException('Symlink media folder/file was skipped.');
        clearstatcache(true, $path);
        $before = @fileperms($path);
        if ($before === false) throw new RuntimeException('Cannot read file permissions.');
        if (PHP_OS_FAMILY === 'Windows') return ['before' => 'Windows ACL', 'after' => 'Windows ACL', 'changed' => false];
        $wanted = $directory ? 0755 : 0644;
        if (($before & 0777) !== $wanted && !@chmod($path, $wanted)) throw new RuntimeException('Cannot set permissions. File ownership or hosting policy requires hosting support.');
        clearstatcache(true, $path);
        $after = @fileperms($path);
        if (($after & 0777) !== $wanted) throw new RuntimeException('Permissions did not change to the expected mode. Check hosting ownership.');
        return ['before' => sprintf('%04o', $before & 0777), 'after' => sprintf('%04o', $after & 0777), 'changed' => ($before & 0777) !== $wanted];
    }
    public function start($documentRoot = '')
    {
        $files = []; $entries = []; $folderCount = 0; $folderChanges = 0;
        $uploadRoot = $this->root . 'uploads';
        if (!is_dir($uploadRoot)) throw new RuntimeException('Application uploads folder does not exist: ' . $uploadRoot . '. Compare this path with the folder shown in your hosting file manager.');
        $roots = [$uploadRoot];
        while ($roots) {
            $directory = array_shift($roots);
            try {
                $permissions = $this->publicPermissions($directory, true);
                $folderCount++; if ($permissions['changed']) $folderChanges++;
                foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $file) {
                    if ($file->isLink()) continue;
                    $path = str_replace('\\', '/', $file->getPathname());
                    if ($file->isDir()) {
                        // Process public image folders only, never private quote PDFs or admin files.
                        $rel = substr($path, strlen($this->root));
                        if (preg_match('~^uploads/(?:tours|gallery|repaired|recovery|destinations|pages)(?:/|$)~', $rel)) $roots[] = $path;
                    } elseif ($file->isFile() && in_array(strtolower($file->getExtension()), self::EXTENSIONS, true) && $this->inside($path)) {
                        $files[] = substr($path, strlen($this->root));
                    }
                }
            } catch (Throwable $e) {
                $entries[] = ['table'=>'server','id'=>0,'title'=>basename($directory),'field'=>'folder','path'=>substr($directory, strlen($this->root)),'new_path'=>'','status'=>'failed','message'=>$e->getMessage()];
            }
            if ($folderCount + count($roots) > 5000 || count($files) > 50000) throw new RuntimeException('Too many media entries for one job. Contact hosting support to check upload folder layout.');
        }
        sort($files);
        TourImagePaths::publish($this->root, $files);
        $diagnostics = ['application_root' => $this->root, 'uploads_folder' => $uploadRoot, 'document_root' => str_replace('\\', '/', $documentRoot), 'files_found' => count($files), 'folders_checked' => $folderCount, 'folder_permissions_fixed' => $folderChanges];
        $publicRoot = $documentRoot !== '' ? realpath($documentRoot) : false;
        if ($publicRoot && rtrim(str_replace('\\', '/', $publicRoot), '/') . '/' !== $this->root && is_dir($publicRoot . '/uploads')) $diagnostics['warning'] = 'The application and document root differ, and both have uploads folders. Compare these paths with the hosting folder containing your photos before assuming files are missing.';
        return ['files'=>$files,'entries'=>$entries,'diagnostics'=>$diagnostics];
    }
    public function batch(array $files, $cursor, $limit = 8)
    {
        $repair = new TourImageRepair($this->root);
        $report = ['cursor'=>$cursor,'total'=>count($files),'done'=>false,'permissions_fixed'=>0,'valid'=>0,'failed'=>0,'entries'=>[]];
        $began = microtime(true);
        for ($i = $cursor; $i < min($cursor + $limit, count($files)); $i++) {
            $relative = $files[$i]; $absolute = $this->root . $relative;
            $entry = ['table'=>'server','id'=>$i+1,'title'=>basename($relative),'field'=>'existing image','path'=>$relative,'new_path'=>$relative,'status'=>'success','message'=>'Image validated.'];
            try {
                if (!$this->inside($absolute) || !is_file($absolute)) throw new RuntimeException('File disappeared or is outside the image folders. Rescan server images.');
                $permissions = $this->publicPermissions($absolute);
                if ($permissions['changed']) $report['permissions_fixed']++;
                $entry['message'] = 'Permissions ' . $permissions['before'] . ' → ' . $permissions['after'] . '. ';
                if (!is_readable($absolute)) throw new RuntimeException('PHP still cannot read this file after permission repair. Check ownership.');
                $result = $repair->repair($relative);
                $entry['new_path'] = $result['path'];
                if (in_array($result['status'], ['missing','unsupported','too_large'], true)) throw new RuntimeException($entry['message'] . 'Image validation/conversion: ' . $result['status'] . '. Original file preserved.');
                $entry['message'] .= $result['status'] === 'converted' ? 'WebP copy prepared; original preserved.' : 'Image format is readable.';
                $report['valid']++;
            } catch (Throwable $e) { $entry['status']='failed'; $entry['message']=$e->getMessage(); $report['failed']++; }
            $report['entries'][] = $entry; $report['cursor'] = $i+1;
            if (microtime(true) - $began > 5) break;
        }
        $report['done'] = $report['cursor'] >= count($files);
        return $report;
    }
}
