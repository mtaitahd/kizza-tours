'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert/strict');
class Element {
    constructor(tag = 'div') { this.tagName = tag; this.children = []; this.dataset = {}; this.listeners = {}; this.textContent = ''; this.value = ''; this.checked = false; }
    append(...items) { this.children.push(...items); for (const item of items) if (item && typeof item === 'object') item.parentElement = this; }
    replaceChildren(...items) { this.children = []; this.append(...items); }
    addEventListener(name, handler) { this.listeners[name] = handler; }
}
async function main() {
    const ids = new Map(), nodes = [], requests = [];
    const get = id => { if (!ids.has(id)) ids.set(id, new Element()); return ids.get(id); };
    get('resultFilter').value = 'all';
    const initialTour = {
        id:4, title:'Safari sample', counts:{healthy:0, failed:1, recoverable:1, optimize:1, review:0},
        entries:[{field:'image',path:'uploads/missing.jpg',resolved:'',status:'failed',message:'Missing file'}, {field:'hero_image',path:'uploads/old.jpg',resolved:'uploads/old.webp',status:'recoverable',message:'Repairable path'}]
    };
    let replies = [{ok:true, rows:[initialTour], cursor:4, done:true}];
    const context = {
        window:{TOUR_IMAGE_HEALTH:{token:'test-token',siteUrl:'https://example.test'}},
        document:{getElementById:get, createElement:tag=>{const n = new Element(tag);nodes.push(n);return n;},querySelectorAll:()=>nodes.filter(n=>n.dataset.fixTour)},
        URLSearchParams, AbortController, FormData, setTimeout, clearTimeout,
        fetch:async (url, opts) => {
            requests.push(Object.fromEntries(opts.body));
            assert.equal(url, 'fix-tour-images.php');
            const next = replies.shift(); assert.ok(next, 'Unexpected request');
            return {ok:next.ok, status:next.ok?200:500, text:async()=>JSON.stringify(next)};
        }
    };
    vm.runInNewContext(fs.readFileSync(require('path').join(__dirname,'../admin/js/tour-image-health.js'),'utf8'), context);
    for(let i=0;i<10;i++) await new Promise(setImmediate);
    assert.equal(get('tourTotal').textContent, '1');
    assert.equal(get('tourFailed').textContent, '1');
    assert.equal(get('imageFailed').textContent, '2');
    assert.equal(get('imageRecoverable').textContent, '1');
    assert.equal(get('imageOptimize').textContent, '1');
    assert.equal(get('auditRows').children.length, 1);
    const singleFix = nodes.find(n=>n.dataset.fixTour === '4'); assert.ok(singleFix);
    replies.push({ok:true,stage:'gallery',cursor:0,job:'test-job',done:true,backup:'backup.jsonl',result:{checked:2,updated:1,converted:0,success:1,failed:1,review:0,entries:[
        {table:'tour_packages',id:4,title:'Safari sample',field:'hero_image',path:'old.jpg',new_path:'old.webp',status:'success',message:'Repaired'},
        {table:'tour_packages',id:4,title:'Safari sample',field:'image',path:'missing.jpg',new_path:'missing.jpg',status:'failed',message:'Missing original file'}
    ]}}, {ok:true,rows:[{...initialTour,counts:{healthy:1,failed:1,recoverable:0,optimize:0,review:0}}],cursor:4,done:true});
    await singleFix.listeners.click();
    assert.equal(requests[1].tour_id, '4', 'Per-tour fix must target the clicked tour');
    assert.equal(get('resultRows').children.length, 2);
    assert.match(get('totals').textContent, /Success: 1 \| Failed: 1/);
    assert.equal(get('imageFailed').textContent, '1', 'Completed repair refreshes counts');
    get('resultFilter').value = 'failed'; get('resultFilter').listeners.change();
    assert.equal(get('resultRows').children[0].hidden, true);
    assert.equal(get('resultRows').children[1].hidden, false);
    replies.push({ok:false,error:'Simulated MariaDB error'});
    await get('start').listeners.click();
    assert.match(get('status').textContent, /Simulated MariaDB error/);
    assert.equal(get('resultRows').children[0].dataset.status, 'failed');
    assert.equal(get('start').disabled, false, 'Retry remains available');
    get('restoreFile').files = [new File(['backup'], 'backup.zip', {type:'application/zip'})];
    replies.push(
        {ok:true,job:'restore-job',total:1},
        {ok:true,result:{cursor:1,total:1,done:true,entries:[{table:'backup',id:1,title:'Recovered photo',field:'restore',path:'backup/uploads/tours/photo.webp',new_path:'uploads/tours/photo.webp',status:'success',message:'Restored'}]},totals:{restored:1,kept:0,failed:0,ignored:0}},
        {ok:true,stage:'gallery',cursor:0,job:'repair-after-restore',done:true,backup:'backup.jsonl',result:{checked:1,updated:1,converted:0,success:1,failed:0,review:0,entries:[{table:'tour_packages',id:4,title:'Safari sample',field:'image',path:'photo.webp',new_path:'uploads/tours/photo.webp',status:'success',message:'Path repaired'}]}},
        {ok:true,rows:[{...initialTour,counts:{healthy:1,failed:0,recoverable:0,optimize:0,review:0}}],cursor:4,done:true}
    );
    await get('restoreStart').listeners.click();
    assert.deepEqual(requests.slice(-4).map(r=>r.action), ['restore_upload','restore_batch','start','audit'], 'Backup upload is followed by bulk repair and fresh audit');
    assert.equal(requests.at(-4).csrf_token, 'test-token', 'ZIP upload includes CSRF token');
    assert.match(get('restoreStatus').textContent, /Restored: 1/);
    assert.equal(get('imageFailed').textContent, '0');
    assert.equal(get('resultRows').children.length, 2, 'Keep backup and repair outcomes visible together');
    console.log('Image health UI checks passed: audit counts, single-tour fix, success/failure filters, errors and ZIP restore followed by bulk repair.');
}
main().catch(error=>{console.error(error);process.exitCode=1;});
