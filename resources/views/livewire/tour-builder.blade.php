<div
    x-data="tourPicker"
    x-on:keydown.escape.window="cancelPicking()"
    class="tour-picker-root"
>
@if($this->canAccess())
    {{-- Element picker overlay --}}
    <template x-if="picking">
        <div
            x-on:click.stop="pickElement($event)"
            x-on:mousemove="highlightElement($event)"
            x-on:mouseleave="unhighlightElement()"
            class="fixed inset-0 z-[9998] cursor-crosshair"
            style="background: transparent;"
        >
            <div class="fixed top-4 right-4 z-[9999] rounded-full bg-gray-900/90 px-4 py-2 text-xs font-medium text-white shadow-lg backdrop-blur dark:bg-gray-700/90">
                Pick an element
                <span class="mx-1 text-gray-300">•</span>
                <kbd class="rounded border border-white/25 px-1.5 py-0.5 text-[10px] font-mono">Esc</kbd>
                to cancel
            </div>
        </div>
    </template>

    {{-- Highlight outline --}}
    <div
        x-show="picking && highlightStyle.display !== 'none'"
        x-bind:style="`
            position: fixed;
            top: ${highlightStyle.top}px;
            left: ${highlightStyle.left}px;
            width: ${highlightStyle.width}px;
            height: ${highlightStyle.height}px;
            border: 2px solid #2563eb;
            border-radius: 6px;
            background: rgba(37, 99, 235, 0.08);
            pointer-events: none;
            z-index: 9999;
            transition: all 0.1s ease;
        `"
    >
        <div
            class="absolute -top-6 left-0 rounded-md bg-blue-600 px-2 py-0.5 text-[11px] font-medium text-white whitespace-nowrap shadow-sm"
            x-text="highlightTag"
        ></div>
    </div>

    <x-filament-actions::modals />
@endif
</div>

@script
<script>
Alpine.data('tourPicker', () => ({
    picking: false,
    pickingItemKey: null,
    hiddenModalId: null,
    previewModalId: null,
    previewStarted: false,
    previewWatchInterval: null,
    highlightStyle: { top: 0, left: 0, width: 0, height: 0, display: 'none' },
    highlightTag: '',

    init() {
        $wire.set('currentPath', window.location.pathname);

        $wire.on('start-picking', ({ itemKey }) => {
            this.startPicking(itemKey);
        });

        $wire.on('filament-tour-editor::reset-history', () => {
            localStorage.removeItem('tours');
            window.location.reload();
        });

        $wire.on('filament-tour-editor::start-preview', () => {
            this.startPreview();
        });
    },

    startPicking(itemKey) {
        this.pickingItemKey = itemKey;
        this.picking = true;
        this.hiddenModalId = this.getOpenModalId();

        if (this.hiddenModalId) {
            const modalId = this.hiddenModalId;

            window.setTimeout(() => {
                window.dispatchEvent(new CustomEvent('close-modal-quietly', {
                    bubbles: true,
                    composed: true,
                    detail: { id: modalId },
                }));
            }, 75);
        }
    },

    cancelPicking() {
        if (!this.picking) return;
        this.picking = false;
        this.pickingItemKey = null;
        this.highlightStyle = { top: 0, left: 0, width: 0, height: 0, display: 'none' };
        this.highlightTag = '';

        if (this.hiddenModalId) {
            const modalId = this.hiddenModalId;

            window.setTimeout(() => {
                window.dispatchEvent(new CustomEvent('open-modal', {
                    bubbles: true,
                    composed: true,
                    detail: { id: modalId },
                }));
            }, 75);
        }

        this.hiddenModalId = null;
    },

    startPreview() {
        this.previewModalId = this.getOpenModalId();
        this.previewStarted = false;

        if (!this.previewModalId) {
            return;
        }

        const modalId = this.previewModalId;

        window.setTimeout(() => {
            window.dispatchEvent(new CustomEvent('close-modal-quietly', {
                bubbles: true,
                composed: true,
                detail: { id: modalId },
            }));
        }, 75);

        window.clearInterval(this.previewWatchInterval);

        this.previewWatchInterval = window.setInterval(() => {
            const previewIsActive = document.body.classList.contains('driver-active')
                || document.querySelector('.driver-popover') !== null
                || document.querySelector('.driver-overlay') !== null;

            if (previewIsActive) {
                this.previewStarted = true;

                return;
            }

            if (!this.previewStarted) {
                return;
            }

            window.clearInterval(this.previewWatchInterval);
            this.previewWatchInterval = null;
            this.previewStarted = false;

            if (!this.previewModalId) {
                return;
            }

            const modalId = this.previewModalId;

            this.previewModalId = null;

            window.setTimeout(() => {
                window.dispatchEvent(new CustomEvent('open-modal', {
                    bubbles: true,
                    composed: true,
                    detail: { id: modalId },
                }));
            }, 75);
        }, 150);
    },

    hideBuilderElements() {
        const root = this.$root;
        const hidden = [];
        for (const child of root.children) {
            if (child.style.display !== 'none') {
                hidden.push(child);
                child.style.display = 'none';
            }
        }
        return () => hidden.forEach(el => el.style.display = '');
    },

    highlightElement(event) {
        if (!this.picking) return;

        const restore = this.hideBuilderElements();
        const el = document.elementFromPoint(event.clientX, event.clientY);
        restore();

        if (!el || this.isBuilderElement(el)) return;

        const rect = el.getBoundingClientRect();
        this.highlightStyle = {
            top: rect.top,
            left: rect.left,
            width: rect.width,
            height: rect.height,
            display: 'block',
        };
        this.highlightTag = this.getReadableLabel(el);
    },

    unhighlightElement() {
        if (!this.picking) return;
        this.highlightStyle.display = 'none';
        this.highlightTag = '';
    },

    pickElement(event) {
        if (!this.picking) return;

        event.preventDefault();
        event.stopPropagation();

        const restore = this.hideBuilderElements();
        const el = document.elementFromPoint(event.clientX, event.clientY);
        restore();

        if (!el || this.isBuilderElement(el)) return;

        const selector = this.generateSelector(el);
        const itemKey = this.pickingItemKey;

        this.cancelPicking();

        $wire.onElementPicked(selector, itemKey);
    },

    getOpenModalId() {
        const openModals = Array.from(document.querySelectorAll('.fi-modal-open[data-fi-modal-id]'));
        const openModal = openModals.at(-1);

        return openModal?.dataset.fiModalId ?? null;
    },

    generateSelector(el) {
        const isUnique = (sel) => {
            try {
                const matches = document.querySelectorAll(sel);
                return matches.length === 1 && matches[0] === el;
            } catch { return false; }
        };

        const isUniqueOrContains = (sel) => {
            try {
                const matches = document.querySelectorAll(sel);
                return matches.length === 1 && (matches[0] === el || matches[0].contains(el));
            } catch { return false; }
        };

        // 1. Non-Livewire ID
        if (el.id && !el.id.includes('livewire')) {
            return '#' + CSS.escape(el.id);
        }

        // 2. aria-label or title attribute
        for (const attr of ['aria-label', 'title']) {
            const val = el.getAttribute(attr);
            if (val && val.length < 60) {
                const sel = `[${attr}="${CSS.escape(val)}"]`;
                if (isUnique(sel)) return sel;
            }
        }

        // 3. Nearest ancestor or self with href
        const linkEl = el.closest('a[href]');
        if (linkEl) {
            try {
                const path = new URL(linkEl.getAttribute('href'), location.origin).pathname;
                const linkSel = `a[href$="${CSS.escape(path)}"]`;
                if (isUnique(linkSel)) {
                    if (linkEl !== el) {
                        const childPart = this.buildElementPart(el);
                        const scoped = linkSel + ' ' + childPart;
                        if (isUnique(scoped)) return scoped;
                    }
                    return linkSel;
                }
            } catch {}
        }

        // 4. Buttons
        if (el.tagName === 'BUTTON' || el.closest('button')) {
            const btn = el.tagName === 'BUTTON' ? el : el.closest('button');
            const label = btn.getAttribute('aria-label');
            if (label) {
                const sel = `button[aria-label="${CSS.escape(label)}"]`;
                if (isUniqueOrContains(sel)) {
                    if (btn !== el) {
                        const scoped = sel + ' ' + this.buildElementPart(el);
                        if (isUnique(scoped)) return scoped;
                    }
                    return sel;
                }
            }
            const type = btn.getAttribute('type');
            if (type && type !== 'button') {
                const sel = `button[type="${type}"]`;
                if (isUniqueOrContains(sel)) return btn === el ? sel : sel + ' ' + this.buildElementPart(el);
            }
        }

        // 5. Stable data attributes
        const skipDataAttrs = ['data-livewire', 'data-id', 'wire:', 'x-'];
        for (const attr of el.attributes) {
            if (attr.name.startsWith('data-') && !skipDataAttrs.some(s => attr.name.includes(s)) && attr.value.length < 80) {
                const sel = `[${attr.name}="${CSS.escape(attr.value)}"]`;
                if (isUnique(sel)) return sel;
            }
        }

        // 6. Tag + stable classes
        const elPart = this.buildElementPart(el);
        const elPartNth = elPart + this.nthChild(el);

        if (isUnique(elPart)) return elPart;
        if (isUnique(elPartNth)) return elPartNth;

        // 7. Anchor on nearest unique ancestor
        const anchors = [
            () => {
                const a = el.parentElement?.closest('a[href]');
                if (!a) return null;
                try {
                    const path = new URL(a.getAttribute('href'), location.origin).pathname;
                    return `a[href$="${CSS.escape(path)}"]`;
                } catch { return null; }
            },
            () => {
                const wn = el.closest('[wire\\:name]');
                if (!wn || wn === document.body) return null;
                return `[wire\\:name="${CSS.escape(wn.getAttribute('wire:name'))}"]`;
            },
            () => {
                const idEl = el.closest('[id]:not([id*="livewire"])');
                if (!idEl || idEl === document.body) return null;
                return '#' + CSS.escape(idEl.id);
            },
        ];

        for (const getAnchor of anchors) {
            const anchor = getAnchor();
            if (!anchor) continue;
            for (const part of [elPartNth, elPart]) {
                const candidate = anchor + ' ' + part;
                if (isUnique(candidate)) return candidate;
            }
        }

        // 8. Walk up ancestors
        const parts = [elPartNth];
        let current = el.parentElement;
        for (let depth = 0; depth < 5 && current && current !== document.body; depth++) {
            if (current.id && !current.id.includes('livewire')) {
                parts.unshift('#' + CSS.escape(current.id));
                const candidate = parts.join(' > ');
                if (isUnique(candidate)) return candidate;
                break;
            }

            const wireName = current.getAttribute('wire:name');
            if (wireName) {
                parts.unshift(`[wire\\:name="${CSS.escape(wireName)}"]`);
                const candidate = parts.join(' ');
                if (isUnique(candidate)) return candidate;
            }

            let part = this.buildElementPart(current) + this.nthChild(current);
            parts.unshift(part);
            const candidate = parts.join(' > ');
            if (isUnique(candidate)) return candidate;

            current = current.parentElement;
        }

        return parts.join(' > ');
    },

    buildElementPart(node) {
        const isStateClass = (c) => /^(fi-)?(active|open|closed|selected|expanded|collapsed|focused|disabled|checked|highlighted|visible|hidden|loading|pending|entering|leaving|current)/i.test(c);
        const tag = node.tagName.toLowerCase();
        const classes = Array.from(node.classList).filter(c =>
            c.length > 2 && !c.includes(':') && !c.includes('[') &&
            !c.match(/^(livewire|wire|alpine|x-)/) && !isStateClass(c)
        );
        if (classes.length > 0) {
            return tag + classes.slice(0, 3).map(c => '.' + CSS.escape(c)).join('');
        }
        return tag;
    },

    nthChild(node) {
        const parent = node.parentElement;
        if (!parent) return '';
        const part = this.buildElementPart(node);
        const siblings = Array.from(parent.children).filter(c => this.buildElementPart(c) === part);
        if (siblings.length > 1) {
            return ':nth-child(' + (Array.from(parent.children).indexOf(node) + 1) + ')';
        }
        return '';
    },

    getReadableLabel(el) {
        const tag = el.tagName.toLowerCase();
        const text = (el.textContent || '').trim().substring(0, 30);
        const fiClass = Array.from(el.classList || []).find(c => c.startsWith('fi-'));

        if (fiClass) return fiClass;
        if (el.id) return '#' + el.id;
        if (text) return `<${tag}> "${text}${text.length >= 30 ? '...' : ''}"`;
        return `<${tag}>`;
    },

    isBuilderElement(el) {
        return el.closest('.tour-picker-root') !== null;
    },
}));
</script>
@endscript
