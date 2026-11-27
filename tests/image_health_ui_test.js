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
        URLSearchParams, AbortController, setTimeout, clearTimeout,
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
    console.log('Image health UI checks passed: audit counts, single-tour fix, rescan, success/failure filters and request errors.');
}
main().catch(error=>{console.error(error);process.exitCode=1;});
