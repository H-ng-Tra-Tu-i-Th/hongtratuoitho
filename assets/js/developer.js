'use strict';
document.querySelectorAll('[data-hdd-print]').forEach(button => {
  button.hidden = false;
  button.addEventListener('click', () => window.print());
});

// Draw only connections between server-rendered, escaped nodes. No Mermaid runtime or remote service.
(() => {
  const ns = 'http://www.w3.org/2000/svg';
  const svgElement = (name, attributes = {}) => {
    const element = document.createElementNS(ns, name);
    Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
    return element;
  };
  const diagrams = [];
  document.querySelectorAll('[data-hdd-flow]').forEach((figure, index) => {
    const scroll = figure.querySelector('.hdd-flow-scroll');
    const canvas = figure.querySelector('.hdd-flow-canvas');
    const fallback = figure.querySelector('.hdd-flow-text');
    try {
      const edges = JSON.parse(canvas.dataset.hddEdges);
      const nodes = new Map([...canvas.querySelectorAll('[data-hdd-node]')].map(node => [node.dataset.hddNode, node]));
      if (!Array.isArray(edges) || edges.some(edge => !nodes.has(edge.from) || !nodes.has(edge.to))) return;
      const markerId = `hdd-arrow-${index}`;
      let lastWidth = 0;
      const draw = () => {
        const previous = canvas.querySelector('svg');
        const bounds = canvas.getBoundingClientRect();
        if (!bounds.width || !bounds.height) return;
        const svg = svgElement('svg', {viewBox: `0 0 ${bounds.width} ${bounds.height}`, 'aria-hidden': 'true', focusable: 'false'});
        const defs = svgElement('defs');
        const marker = svgElement('marker', {id: markerId, viewBox: '0 0 10 10', refX: 9, refY: 5, markerWidth: 7, markerHeight: 7, orient: 'auto'});
        marker.append(svgElement('path', {d: 'M 0 0 L 10 5 L 0 10 z', fill: '#8c6261'}));
        defs.append(marker); svg.append(defs);
        edges.forEach(edge => {
          const source = nodes.get(edge.from).getBoundingClientRect();
          const target = nodes.get(edge.to).getBoundingClientRect();
          const x1 = source.left + source.width / 2 - bounds.left;
          const y1 = source.bottom - bounds.top;
          const x2 = target.left + target.width / 2 - bounds.left;
          const y2 = target.top - bounds.top - 3;
          let path, labelX, labelY;
          // A long connection uses the outside gutter so it cannot cross intermediate cards.
          if (y2 - y1 > 100) {
            const side = x1 <= bounds.width / 2 ? 16 : bounds.width - 16;
            path = `M ${x1} ${y1} V ${y1 + 18} H ${side} V ${y2 - 22} H ${x2} V ${y2}`;
            labelX = (side + x1) / 2; labelY = y1 + 12;
          } else {
            const middle = y1 + (y2 - y1) / 2;
            path = `M ${x1} ${y1} V ${middle} H ${x2} V ${y2}`;
            labelX = x2; labelY = middle - 7;
          }
          svg.append(svgElement('path', {d: path, fill: 'none', stroke: '#8c6261', 'stroke-width': 1.7, 'stroke-linejoin': 'round', 'marker-end': `url(#${markerId})`}));
          if (edge.label) {
            const label = svgElement('text', {x: labelX, y: labelY, 'text-anchor': 'middle', class: 'hdd-edge-label'});
            label.textContent = edge.label;
            svg.append(label);
          }
        });
        if (previous) previous.replaceWith(svg); else canvas.prepend(svg);
        if (lastWidth !== scroll.clientWidth) {
          scroll.scrollLeft = Math.max(0, (scroll.scrollWidth - scroll.clientWidth) / 2);
          lastWidth = scroll.clientWidth;
        }
        const hint = figure.querySelector('.hdd-flow-hint');
        if (hint) hint.hidden = scroll.scrollWidth <= scroll.clientWidth + 1;
      };
      scroll.hidden = false;
      draw();
      if (!canvas.querySelector('svg')) { scroll.hidden = true; return; }
      fallback.open = false;
      figure.classList.add('hdd-flow-ready');
      diagrams.push(draw);
      if (typeof ResizeObserver !== 'undefined') {
        let scheduled = false;
        new ResizeObserver(() => {
          if (scheduled) return;
          scheduled = true;
          requestAnimationFrame(() => { scheduled = false; draw(); });
        }).observe(canvas);
      }
    } catch (_) {
      scroll.hidden = true;
      fallback.open = true;
    }
  });
  window.addEventListener('resize', () => diagrams.forEach(draw => draw()));
  if (document.fonts) document.fonts.ready.then(() => diagrams.forEach(draw => draw()));
  let printState = [];
  window.addEventListener('beforeprint', () => {
    printState = [...document.querySelectorAll('.hdd-flow-text')].map(detail => [detail, detail.open]);
    printState.forEach(([detail]) => { detail.open = true; });
  });
  window.addEventListener('afterprint', () => {
    printState.forEach(([detail, open]) => { detail.open = open; });
    printState = [];
  });
})();

// v19.14.4: collapse auxiliary navigation only when the document grid stacks.
(() => {
 const shell=document.querySelector('.hdd-shell');if(!shell)return;
 const items=[...document.querySelectorAll('.hdd-history,.hdd-toc')];const touched=new WeakSet();
 items.forEach(item=>item.querySelector('summary')?.addEventListener('click',()=>touched.add(item)));
 const sync=()=>{const columns=getComputedStyle(shell).gridTemplateColumns.trim().split(/\s+/).length;items.forEach(item=>{if(!touched.has(item))item.open=columns>1;});};
 sync();if('ResizeObserver' in window)new ResizeObserver(sync).observe(shell);else window.addEventListener('resize',sync);
})();
