/* Tour image audit and batched repair dashboard. */
(() => {
    'use strict';
    const config = window.TOUR_IMAGE_HEALTH;
    const el = id => document.getElementById(id);
    let busy = false, running = false, started = false, stage = 'tours', cursor = 0, target = 0, job = '';
    let audit = [], totals = {}, resultRows = [];
    let restoreJob = config.restoreJob || '';
    let serverJob = config.serverJob || '';
    const labels = {healthy:'Available', failed:'Failed', recoverable:'Path can be repaired', optimize:'WebP conversion available', review:'Needs review', success:'Success'};
    function node(tag, text, cls) {
        const item = document.createElement(tag);
        if (text !== undefined) item.textContent = text;
        if (cls) item.className = cls;
        return item;
    }
    function controls() {
        el('scan').disabled = busy || started;
        el('start').disabled = busy;
        el('start').textContent = started ? 'Resume Repair' : 'Fix All Tour Images';
        el('stop').disabled = !running;
        el('restoreStart').disabled = busy || started;
        el('restoreFile').disabled = busy || started;
        el('restoreStart').textContent = restoreJob ? 'Resume Backup Restore' : 'Restore Images from ZIP';
        el('serverStart').disabled = busy || started;
        el('serverStart').textContent = serverJob ? 'Resume Server Image Repair' : 'Fix Images Already on Server';
        document.querySelectorAll('[data-fix-tour]').forEach(button => { button.disabled = busy || started; });
    }
    async function request(data) {
        const abort = new AbortController();
        const timeout = setTimeout(() => abort.abort(), 60000);
        try {
            const response = await fetch('fix-tour-images.php', {
                method:'POST', credentials:'same-origin', signal:abort.signal,
                body:(typeof FormData !== 'undefined' && data instanceof FormData) ? data : new URLSearchParams(Object.assign({csrf_token:config.token}, data))
            });
            const text = await response.text();
            let result;
            try { result = JSON.parse(text); }
            catch (_) { throw new Error('Server returned an invalid response (HTTP ' + response.status + '). ' + text.replace(/<[^>]+>/g, ' ').slice(0, 350)); }
            if (!response.ok || !result.ok) throw new Error(result.error || 'Request failed (HTTP ' + response.status + ').');
            return result;
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('Request timed out. Retry to continue; original images are preserved.');
            throw error;
        } finally { clearTimeout(timeout); }
    }
    function preview(path, parent) {
        if (!path) return;
        const img = node('img', undefined, 'image-preview mb-1');
        img.alt = 'Tour photo'; img.loading = 'lazy';
        img.src = /^https?:\/\//i.test(path) ? path : config.siteUrl + '/' + path.split('/').map(encodeURIComponent).join('/');
        img.addEventListener('error', () => {
            img.hidden = true;
            parent.append(node('div', 'Browser could not load this photo. Check its URL and server access permissions.', 'small status-failed'));
        }, {once:true});
        parent.append(img);
    }
    function renderAudit() {
        const affected = audit.filter(t => t.counts.failed + t.counts.recoverable > 0);
        const sum = key => audit.reduce((n, t) => n + t.counts[key], 0);
        el('tourTotal').textContent = String(audit.length);
        el('tourFailed').textContent = String(affected.length);
        el('imageFailed').textContent = String(sum('failed') + sum('recoverable'));
        el('imageRecoverable').textContent = String(sum('recoverable'));
        el('imageOptimize').textContent = String(sum('optimize'));
        const body = el('auditRows'); body.replaceChildren();
        const displayed = el('showAll').checked ? audit : audit.filter(t => t.counts.failed + t.counts.recoverable + t.counts.optimize + t.counts.review > 0);
        for (const tour of displayed) {
            const tr = node('tr'); const name = node('td');
            name.append(node('strong', tour.title), node('div', 'Tour #' + tour.id, 'small text-muted'));
            const detail = node('td');
            for (const entry of tour.entries) {
                if (entry.status === 'healthy' && !el('showAll').checked) continue;
                const block = node('div', undefined, 'mb-3');
                block.append(node('div', entry.field + ': ' + labels[entry.status], entry.status === 'failed' ? 'font-weight-bold status-failed' : 'font-weight-bold'));
                block.append(node('div', entry.message, 'small'));
                if (entry.path) block.append(node('div', entry.path, 'image-note text-muted'));
                preview(entry.resolved || (/^https?:\/\//i.test(entry.path) ? entry.path : ''), block);
                detail.append(block);
            }
            const actions = node('td');
            const fix = node('button', 'Fix Images', 'btn btn-sm btn-success mb-2');
            fix.dataset.fixTour = String(tour.id); fix.disabled = busy || started;
            fix.addEventListener('click', () => runRepair(tour.id));
            const edit = node('a', 'Edit Tour', 'btn btn-sm btn-outline-secondary');
            edit.href = 'tours?edit_tour_id=' + tour.id;
            actions.append(fix, node('br'), edit); tr.append(name, detail, actions); body.append(tr);
        }
        el('emptyAudit').hidden = displayed.length > 0;
        el('emptyAudit').textContent = audit.length ? 'No image problems found in the completed scan.' : 'No tours found.';
    }
    function renderResults() {
        const filter = el('resultFilter').value;
        for (const row of resultRows) row.hidden = filter !== 'all' && row.dataset.status !== filter;
    }
    function appendResults(entries) {
        el('emptyResults').hidden = entries.length > 0 || resultRows.length > 0;
        for (const entry of entries) {
            const tr = node('tr'); tr.dataset.status = entry.status;
            const record = node('td');
            record.append(node('strong', entry.title || entry.table + ' #' + entry.id), node('div', entry.table + ' #' + entry.id + ' / ' + entry.field, 'small'));
            if (entry.path) record.append(node('div', entry.path, 'image-note text-muted'));
            const status = node('td', labels[entry.status] || entry.status, 'font-weight-bold status-' + entry.status);
            const detail = node('td'); detail.append(node('div', entry.message));
            if (entry.new_path && entry.new_path !== entry.path) detail.append(node('div', 'New path: ' + entry.new_path, 'image-note text-muted'));
            tr.append(record, status, detail); el('resultRows').append(tr); resultRows.push(tr);
        }
        renderResults();
    }
    async function scanImages(preserveStatus = false) {
        const previousStatus = el('status').textContent;
        busy = true; controls(); audit = []; renderAudit(); let after = 0;
        try {
            while (true) {
                el('status').textContent = 'Scanning tour photos... ' + audit.length + ' tours checked.';
                const result = await request({action:'audit', cursor:String(after)});
                audit.push(...result.rows); after = result.cursor; renderAudit();
                if (result.done) break;
            }
            el('status').textContent = preserveStatus ? previousStatus : 'Scan complete. ' + audit.length + ' tours checked. Click Fix Images for one tour or Fix All Tour Images.';
        } catch (error) {
            el('status').textContent = 'Scan failed after ' + audit.length + ' tours: ' + error.message;
            el('emptyAudit').textContent = 'Scan incomplete. Use Rescan Images to retry.';
            el('emptyAudit').hidden = false;
        } finally { busy = false; controls(); }
    }
    async function runRepair(tourId = 0, keepResults = false) {
        if (busy) return;
        if (!started) {
            target = tourId; stage = 'tours'; cursor = 0; job = '';
            totals = {checked:0, updated:0, converted:0, success:0, failed:0, review:0};
            if (!keepResults) { resultRows = []; el('resultRows').replaceChildren(); el('emptyResults').hidden = false; }
            el('totals').textContent = ''; el('backup').textContent = '';
        }
        busy = true; running = true; controls(); let completed = false;
        try {
            while (running) {
                el('status').textContent = 'Repairing ' + (target ? 'Tour #' + target + ': ' : '') + stage + ' after record #' + cursor + '...';
                const data = {action:started?'batch':'start', stage, cursor:String(cursor), tour_id:String(target)};
                if (job) data.job = job;
                const result = await request(data);
                started = true; stage = result.stage; cursor = result.cursor; job = result.job;
                for (const key of Object.keys(totals)) totals[key] += result.result[key] || 0;
                appendResults(result.result.entries);
                el('totals').textContent = 'Success: ' + totals.success + ' | Failed: ' + totals.failed + ' | Needs review: ' + totals.review + ' | Fields updated: ' + totals.updated + ' | WebP references prepared: ' + totals.converted;
                el('backup').textContent = 'Previous path backup on server: cache/' + result.backup;
                if (result.done) {
                    completed = true; running = false; started = false;
                    el('status').textContent = 'Repair complete: ' + totals.success + ' successful, ' + totals.failed + ' failed, ' + totals.review + ' need review. See the results below for exact reasons.';
                    break;
                }
            }
            if (started) el('status').textContent = 'Paused. Click Resume Repair to continue.';
        } catch (error) {
            running = false;
            el('status').textContent = 'Repair request failed: ' + error.message;
            appendResults([{table:'request', id:cursor, field:stage, path:'', new_path:'', status:'failed', message:error.message}]);
        } finally { busy = false; running = false; controls(); }
        if (completed) await scanImages(true);
    }
    async function restoreBackup() {
        if (busy || started) return;
        const file = el('restoreFile').files && el('restoreFile').files[0];
        if (!file && !restoreJob) { el('restoreStatus').textContent = 'Choose an image backup ZIP first.'; return; }
        busy = true; controls(); let completed = false;
        try {
            if (file) {
                resultRows = []; el('resultRows').replaceChildren(); el('emptyResults').hidden = false;
                el('restoreStatus').textContent = 'Uploading image backup...';
                const data = new FormData();
                data.append('csrf_token', config.token); data.append('action', 'restore_upload'); data.append('backup', file);
                const uploaded = await request(data); restoreJob = uploaded.job; el('restoreFile').value = '';
            }
            while (true) {
                const reply = await request({action:'restore_batch', job:restoreJob});
                appendResults(reply.result.entries);
                const t = reply.totals;
                el('restoreStatus').textContent = 'Backup entries checked: ' + reply.result.cursor + '/' + reply.result.total + '. Restored: ' + t.restored + ' | Existing files kept: ' + t.kept + ' | Failed: ' + t.failed + ' | Non-image entries ignored: ' + t.ignored;
                if (reply.result.done) { restoreJob = ''; completed = true; break; }
            }
        } catch (error) {
            el('restoreStatus').textContent = 'Restore stopped: ' + error.message + ' Resume or upload the ZIP again to retry.';
        } finally { busy = false; controls(); }
        if (completed) {
            el('restoreStatus').textContent += '. Restore complete. Repairing all tour image references next.';
            await runRepair(0, true);
        }
    }
    async function verifyServerImages(entries) {
        let failed = 0, review = 0;
        for (let i = 0; i < entries.length; i += 4) {
            await Promise.all(entries.slice(i, i + 4).map(async entry => {
                if (entry.field !== 'existing image') return;
                const path = entry.new_path || entry.path;
                const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 15000);
                try {
                    const url = config.siteUrl + '/' + path.split('/').map(encodeURIComponent).join('/') + '?image_check=' + Date.now();
                    const response = await fetch(url, {method:'HEAD', credentials:'same-origin', cache:'no-store', signal:controller.signal});
                    const type = (response.headers.get('content-type') || '').split(';')[0].trim().toLowerCase();
                    if (response.status === 405 || response.status === 501) {
                        review++; if (entry.status === 'success') entry.status = 'review';
                        entry.message += ' Server does not support HEAD verification; open the image URL to check it.';
                    } else if (!response.ok || !type.startsWith('image/')) {
                        failed++; entry.status = 'failed';
                        entry.message += ' Browser HTTP check: ' + response.status + ', Content-Type: ' + (type || 'missing') + '. ' + (response.status === 403 ? 'Server access denied; check ownership, hotlink protection or hosting rules.' : 'Check the public image URL and document root.');
                    } else if (path.toLowerCase().endsWith('.webp') && type !== 'image/webp') {
                        review++; if (entry.status === 'success') entry.status = 'review';
                        entry.message += ' HTTP ' + response.status + ' but WebP Content-Type is ' + type + '; check server MIME configuration.';
                    } else { entry.message += ' Browser HTTP ' + response.status + ', ' + type + '.'; }
                } catch (error) {
                    review++; if (entry.status === 'success') entry.status = 'review';
                    entry.message += ' HTTP verification could not finish: ' + error.message;
                } finally { clearTimeout(timeout); }
            }));
        }
        return {failed, review};
    }
    async function repairServerImages() {
        if (busy || started) return;
        busy = true; controls(); let completed = false, httpFailed = 0, httpReview = 0;
        try {
            if (!serverJob) {
                resultRows = []; el('resultRows').replaceChildren(); el('emptyResults').hidden = false;
                el('serverStatus').textContent = 'Finding existing images and checking media folder permissions...';
                const start = await request({action:'server_start'}); serverJob = start.job;
                appendResults(start.entries);
                const d = start.diagnostics;
                el('serverDiagnostics').textContent = 'Application root: ' + d.application_root + '\nUploads folder checked: ' + d.uploads_folder + '\nDocument root: ' + d.document_root + '\nImages found: ' + d.files_found + '\nFolders checked: ' + d.folders_checked + '\nFolder permissions fixed: ' + d.folder_permissions_fixed + (d.warning ? '\nWARNING: ' + d.warning : '');
            }
            while (true) {
                const reply = await request({action:'server_batch', job:serverJob});
                el('serverStatus').textContent = 'Checking public image access... ' + reply.result.cursor + '/' + reply.result.total;
                const http = await verifyServerImages(reply.result.entries);
                httpFailed += http.failed; httpReview += http.review;
                appendResults(reply.result.entries);
                const t = reply.totals;
                el('serverStatus').textContent = 'Server images checked: ' + reply.result.cursor + '/' + reply.result.total + ' | File permissions fixed: ' + t.permissions_fixed + ' | Formats validated: ' + t.valid + ' | File errors: ' + t.failed + ' | HTTP failures this run: ' + httpFailed + ' | HTTP checks needing review: ' + httpReview;
                if (reply.result.done) { serverJob = ''; completed = true; break; }
            }
        } catch (error) { el('serverStatus').textContent = 'Server image repair stopped: ' + error.message + ' Retry to continue.'; }
        finally { busy = false; controls(); }
        if (completed) {
            el('serverStatus').textContent += '. Server scan complete. Repairing package image references next.';
            await runRepair(0, true);
        }
    }
    el('scan').addEventListener('click', () => scanImages());
    el('start').addEventListener('click', () => runRepair());
    el('stop').addEventListener('click', () => { running = false; el('stop').disabled = true; });
    el('showAll').addEventListener('change', renderAudit);
    el('resultFilter').addEventListener('change', renderResults);
    el('restoreStart').addEventListener('click', restoreBackup);
    el('serverStart').addEventListener('click', repairServerImages);
    scanImages();
})();
