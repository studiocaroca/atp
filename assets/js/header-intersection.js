// Draws the exact intersection of the header's animated rectangle (.header-brand-box::before)
// and the animated circle (.header-corner-dot) on a canvas layer, every frame.
// Reads their live computed transform/position only — never duplicates or animates on its own.
(function () {
    var CELESTE = '#A9E3FB';

    function init() {
        var header = document.querySelector('.header');
        var box = document.querySelector('.header-brand-box');
        var circle = document.querySelector('.header-corner-dot');
        var canvas = document.querySelector('.header-intersection');
        if (!header || !box || !circle || !canvas) return;

        var ctx = canvas.getContext('2d');
        var dpr = 1;
        var eyebrowStack = document.querySelector('.header-eyebrow-stack');
        var fillClip = document.querySelector('.header-eyebrow-fill-clip');
        var fillCircle = document.querySelector('.header-eyebrow-fill-circle');
        var fillText = document.querySelector('.header-eyebrow--fill');

        // On mobile the box and circle deliberately bleed well past the
        // header's own bottom edge (into #quienes-somos, per design) and can
        // overlap each other down there too. Sizing the canvas to just the
        // header's own rect clipped that overlap out of its drawable area,
        // so the collision below the header's bottom edge rendered as the
        // box's raw dark-blue with no celeste correction — a visible gap
        // between the celeste patch above and the circle's plain ocre
        // below. Extend the canvas down to the lower of the box/circle's
        // live bottoms (plus a margin for the transform-driven animations'
        // reach) instead, so it always covers wherever they can overlap.
        // Left/right stay pinned to the header's own width — the box and
        // header already span it exactly, and widening the canvas past it
        // (like the vertical fix does) would bleed past the viewport's
        // right edge and cause horizontal scroll, unlike a bottom bleed
        // which .header's overflow: visible already handles safely.
        var MARGIN = 60;

        function resize() {
            var headerRect = header.getBoundingClientRect();
            var boxRect = box.getBoundingClientRect();
            var circleRect = circle.getBoundingClientRect();
            dpr = window.devicePixelRatio || 1;

            var width = headerRect.width;
            var bottom = Math.max(headerRect.bottom, boxRect.bottom, circleRect.bottom) + MARGIN;
            var height = bottom - headerRect.top;

            canvas.style.left = '0px';
            canvas.style.top = '0px';
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';

            canvas.width = Math.max(1, Math.round(width * dpr));
            canvas.height = Math.max(1, Math.round(height * dpr));
        }

        // Parses a computed clip-path polygon string into local [x, y] points (in px,
        // resolved against the element's own box) — no assumptions about the shape's values.
        // Handles calc() terms, since getComputedStyle does not pre-resolve them to px.
        function parsePolygonPoints(clipPathValue, refWidth, refHeight) {
            if (!clipPathValue) return null;
            var start = clipPathValue.indexOf('polygon(');
            if (start === -1) return null;
            start += 'polygon('.length;
            var depth = 1, end = start;
            while (end < clipPathValue.length && depth > 0) {
                if (clipPathValue[end] === '(') depth++;
                else if (clipPathValue[end] === ')') depth--;
                if (depth > 0) end++;
            }
            var inner = clipPathValue.slice(start, end);
            return inner.split(',').map(function (pair) {
                var m = /^\s*(calc\([^)]*\)|\S+)\s+(calc\([^)]*\)|\S+)\s*$/.exec(pair.trim());
                if (!m) return [0, 0];
                return [resolveLength(m[1], refWidth), resolveLength(m[2], refHeight)];
            });
        }

        function resolveLength(token, ref) {
            token = token.trim();
            if (token.indexOf('calc(') === 0) {
                token = token.slice(5, -1);
            }
            var total = 0, found = false;
            var re = /([+-]?)\s*([\d.]+)(px|%)/g;
            var m;
            while ((m = re.exec(token))) {
                found = true;
                var sign = m[1] === '-' ? -1 : 1;
                var num = parseFloat(m[2]);
                var val = m[3] === '%' ? (num / 100) * ref : num;
                total += sign * val;
            }
            return found ? total : (parseFloat(token) || 0);
        }

        function getBoxPolygon() {
            var boxRect = box.getBoundingClientRect();
            var cs = getComputedStyle(box, '::before');

            var top = parseFloat(cs.top) || 0;
            var right = parseFloat(cs.right) || 0;
            var bottom = parseFloat(cs.bottom) || 0;
            var left = parseFloat(cs.left) || 0;

            var beforeLeft = boxRect.left + left;
            var beforeTop = boxRect.top + top;
            var beforeRight = boxRect.right - right;
            var beforeBottom = boxRect.bottom - bottom;
            var width = beforeRight - beforeLeft;
            var height = beforeBottom - beforeTop;

            var localPoints = parsePolygonPoints(cs.clipPath, width, height);
            if (!localPoints) return null;

            var matrix = new DOMMatrix(cs.transform === 'none' ? undefined : cs.transform);
            var cx = width / 2;
            var cy = height / 2;

            return localPoints.map(function (pt) {
                var p = matrix.transformPoint({ x: pt[0] - cx, y: pt[1] - cy });
                return [beforeLeft + cx + p.x, beforeTop + cy + p.y];
            });
        }

        function getCircle() {
            var rect = circle.getBoundingClientRect();
            return {
                x: rect.left + rect.width / 2,
                y: rect.top + rect.height / 2,
                r: rect.width / 2,
            };
        }

        // Positions the dark-blue eyebrow text copy so it is clipped by the exact same
        // polygon (box edge) and circle used to paint the canvas above — not an approximation.
        // The polygon clip-path is set directly on .header-eyebrow-fill-clip, in coordinates
        // relative to the stack. The circle can't be combined into that same clip-path (CSS
        // only supports one shape per element), so it's a nested wrapper instead: a
        // border-radius:50% box positioned/sized to match the live circle, with the text
        // pushed back by that wrapper's own offset so it still lines up with the base copy.
        function updateEyebrowFill(polygon, circ) {
            if (!eyebrowStack || !fillClip || !fillCircle || !fillText) return;
            if (!polygon || !circ) {
                fillClip.style.clipPath = 'polygon(0 0, 0 0, 0 0)';
                return;
            }

            var stackRect = eyebrowStack.getBoundingClientRect();

            var clipPoints = polygon.map(function (pt) {
                return (pt[0] - stackRect.left) + 'px ' + (pt[1] - stackRect.top) + 'px';
            });
            fillClip.style.clipPath = 'polygon(' + clipPoints.join(', ') + ')';

            var circleLeft = (circ.x - circ.r) - stackRect.left;
            var circleTop = (circ.y - circ.r) - stackRect.top;
            var circleSize = circ.r * 2;
            fillCircle.style.left = circleLeft + 'px';
            fillCircle.style.top = circleTop + 'px';
            fillCircle.style.width = circleSize + 'px';
            fillCircle.style.height = circleSize + 'px';

            fillText.style.left = (-circleLeft) + 'px';
            fillText.style.top = (-circleTop) + 'px';
        }

        function draw() {
            // Freshly read every frame (not the resize-time canvasRect,
            // which would drift out of sync with the live polygon/circle
            // viewport coordinates below as soon as the page scrolls).
            var canvasViewportRect = canvas.getBoundingClientRect();
            var polygon = getBoxPolygon();
            var circ = getCircle();

            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.globalCompositeOperation = 'source-over';

            var polygonPath = null;
            var circlePath = null;

            if (polygon) {
                polygonPath = new Path2D();
                polygon.forEach(function (pt, i) {
                    var x = pt[0] - canvasViewportRect.left;
                    var y = pt[1] - canvasViewportRect.top;
                    if (i === 0) polygonPath.moveTo(x, y);
                    else polygonPath.lineTo(x, y);
                });
                polygonPath.closePath();

                circlePath = new Path2D();
                circlePath.arc(circ.x - canvasViewportRect.left, circ.y - canvasViewportRect.top, circ.r, 0, Math.PI * 2);

                ctx.fillStyle = CELESTE;
                ctx.fill(polygonPath);

                ctx.globalCompositeOperation = 'destination-in';
                ctx.fill(circlePath);
                ctx.globalCompositeOperation = 'source-over';
            }

            updateEyebrowFill(polygon, circ);

            requestAnimationFrame(draw);
        }

        resize();
        window.addEventListener('resize', resize);
        requestAnimationFrame(draw);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
