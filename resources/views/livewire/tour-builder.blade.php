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
            class="fixed inset-0 z-[9998]"
            x-bind:style="`background: transparent; z-index: 2147483646; cursor: ${interacting ? interactionCursor : 'crosshair'};`"
        ></div>
    </template>

    {{-- Highlight outline --}}
    <div
        x-show="picking && ! interacting && highlightStyle.display !== 'none'"
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
            z-index: 2147483647;
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
    interacting: false,
    shiftPressed: false,
    pointerPosition: { clientX: null, clientY: null },
    pickingItemKey: null,
    pickingItemIndex: null,
    recentlyPickedItemIndex: null,
    recentlyPickedItemTimeout: null,
    hiddenModalId: null,
    previewModalId: null,
    previewStarted: false,
    previewWatchInterval: null,
    isForwardingInteraction: false,
    interactionCursor: 'default',
    pickingClickHandler: null,
    pickingMoveHandler: null,
    pickingLeaveHandler: null,
    pickingKeyDownHandler: null,
    pickingKeyUpHandler: null,
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

    showPickedConfirmation(itemIndex) {
        this.recentlyPickedItemIndex = itemIndex;

        window.clearTimeout(this.recentlyPickedItemTimeout);

        this.recentlyPickedItemTimeout = window.setTimeout(() => {
            this.recentlyPickedItemIndex = null;
            this.recentlyPickedItemTimeout = null;
        }, 2000);
    },

    startPicking(itemKey) {
        this.pickingItemKey = itemKey;
        const normalizedItemKey = String(itemKey).split('.').pop();
        const pickButtons = Array.from(document.querySelectorAll('[data-tour-pick-target]'));
        const activePickButton = pickButtons.find((button) => {
            return button.dataset.tourPickKey === normalizedItemKey
                || button.dataset.tourPickKey === String(itemKey);
        });

        this.pickingItemIndex = activePickButton?.dataset.tourPickIndex !== undefined
            ? Number(activePickButton.dataset.tourPickIndex)
            : null;
        this.picking = true;
        this.interacting = false;
        this.shiftPressed = false;
        this.pointerPosition = { clientX: null, clientY: null };
        this.interactionCursor = 'default';
        this.bindPickingListeners();
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
        this.interacting = false;
        this.shiftPressed = false;
        this.pointerPosition = { clientX: null, clientY: null };
        this.pickingItemKey = null;
        this.interactionCursor = 'default';
        this.unbindPickingListeners();
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

    bindPickingListeners() {
        this.unbindPickingListeners();

        this.pickingClickHandler = (event) => {
            this.pickElement(event);
        };

        this.pickingMoveHandler = (event) => {
            this.highlightElement(event);
        };

        this.pickingLeaveHandler = () => {
            this.unhighlightElement();
        };

        this.pickingKeyDownHandler = (event) => {
            if (event.key !== 'Shift') {
                return;
            }

            this.shiftPressed = true;
            this.interacting = true;
            this.updateInteractionCursor(this.getCurrentPointerTarget());
            this.unhighlightElement();
        };

        this.pickingKeyUpHandler = (event) => {
            if (event.key !== 'Shift') {
                return;
            }

            this.shiftPressed = false;
            this.interacting = false;
            this.interactionCursor = 'default';
        };

        window.addEventListener('click', this.pickingClickHandler, true);
        window.addEventListener('mousemove', this.pickingMoveHandler, true);
        window.addEventListener('mouseleave', this.pickingLeaveHandler, true);
        window.addEventListener('keydown', this.pickingKeyDownHandler, true);
        window.addEventListener('keyup', this.pickingKeyUpHandler, true);
    },

    unbindPickingListeners() {
        if (this.pickingClickHandler) {
            window.removeEventListener('click', this.pickingClickHandler, true);
            this.pickingClickHandler = null;
        }

        if (this.pickingMoveHandler) {
            window.removeEventListener('mousemove', this.pickingMoveHandler, true);
            this.pickingMoveHandler = null;
        }

        if (this.pickingLeaveHandler) {
            window.removeEventListener('mouseleave', this.pickingLeaveHandler, true);
            this.pickingLeaveHandler = null;
        }

        if (this.pickingKeyDownHandler) {
            window.removeEventListener('keydown', this.pickingKeyDownHandler, true);
            this.pickingKeyDownHandler = null;
        }

        if (this.pickingKeyUpHandler) {
            window.removeEventListener('keyup', this.pickingKeyUpHandler, true);
            this.pickingKeyUpHandler = null;
        }
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
        if (!this.picking || this.isForwardingInteraction) return;

        this.pointerPosition = {
            clientX: event.clientX,
            clientY: event.clientY,
        };

        const target = this.getTargetFromPoint(event.clientX, event.clientY);

        if (!target || this.isBuilderElement(target)) {
            if (this.interacting) {
                this.updateInteractionCursor(null);
            }

            return;
        }

        if (this.interacting) {
            this.updateInteractionCursor(target);

            return;
        }

        const rect = target.getBoundingClientRect();
        this.highlightStyle = {
            top: rect.top,
            left: rect.left,
            width: rect.width,
            height: rect.height,
            display: 'block',
        };
        this.highlightTag = this.getReadableLabel(target);
    },

    unhighlightElement() {
        if (!this.picking) return;
        this.highlightStyle.display = 'none';
        this.highlightTag = '';
    },

    pickElement(event) {
        if (!this.picking || this.isForwardingInteraction) return;

        const target = this.getTargetFromPoint(event.clientX, event.clientY);

        if (!target || this.isBuilderElement(target)) return;

        if (event.shiftKey || this.shiftPressed) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation?.();
            this.shiftPressed = true;
            this.interacting = true;
            this.updateInteractionCursor(target);
            this.forwardInteraction(target, event);

            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();

        const selector = this.generateSelector(target);
        const itemKey = this.pickingItemKey;
        const itemIndex = this.pickingItemIndex;

        this.cancelPicking();

        $wire.onElementPicked(selector, itemKey);

        if (itemIndex !== null && itemIndex !== -1) {
            window.setTimeout(() => {
                this.showPickedConfirmation(itemIndex);
            }, 100);
        }
    },

    forwardInteraction(element, event) {
        if (!element || this.isForwardingInteraction) return;

        const mouseEventOptions = {
            bubbles: true,
            cancelable: true,
            composed: true,
            view: window,
            clientX: event.clientX,
            clientY: event.clientY,
            shiftKey: event.shiftKey || this.shiftPressed,
        };

        this.isForwardingInteraction = true;

        if (typeof element.focus === 'function') {
            element.focus({ preventScroll: true });
        }

        element.dispatchEvent(new MouseEvent('mousedown', mouseEventOptions));
        element.dispatchEvent(new MouseEvent('mouseup', mouseEventOptions));
        element.dispatchEvent(new MouseEvent('click', mouseEventOptions));

        window.setTimeout(() => {
            this.isForwardingInteraction = false;

            if (this.picking) {
                this.interacting = this.shiftPressed;

                if (this.interacting) {
                    this.updateInteractionCursor(this.getCurrentPointerTarget());
                } else {
                    this.interactionCursor = 'default';
                }
            }
        }, 0);
    },

    getTargetFromPoint(clientX, clientY) {
        const restore = this.hideBuilderElements();
        const element = document.elementFromPoint(clientX, clientY);
        restore();

        return this.resolveTargetElement(element);
    },

    getCurrentPointerTarget() {
        const { clientX, clientY } = this.pointerPosition;

        if (clientX === null || clientY === null) {
            return null;
        }

        return this.getTargetFromPoint(clientX, clientY);
    },

    updateInteractionCursor(target) {
        if (!this.interacting) {
            this.interactionCursor = 'default';

            return;
        }

        const computedCursor = target ? window.getComputedStyle(target).cursor : null;

        if (computedCursor && computedCursor !== 'auto' && computedCursor !== 'default') {
            this.interactionCursor = computedCursor;

            return;
        }

        this.interactionCursor = this.isInteractiveElement(target) ? 'pointer' : 'default';
    },

    resolveTargetElement(el) {
        if (!el) return null;

        const namedControl = el.matches('input[name], textarea[name], select[name]')
            ? el
            : el.closest('input[name], textarea[name], select[name]');

        if (namedControl) {
            return namedControl;
        }

        const interactive = el.closest([
            'a[href]',
            'button',
            '[role="button"]',
            '[role="menuitem"]',
            '.fi-sidebar-item-btn',
        ].join(', '));

        if (interactive && !this.isBuilderElement(interactive)) {
            return interactive;
        }

        return el;
    },

    isInteractiveElement(el) {
        if (!el) {
            return false;
        }

        return el.matches([
            'a[href]',
            'button',
            '[role="button"]',
            '[role="menuitem"]',
            '.fi-sidebar-item-btn',
        ].join(', '));
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

        const namedControl = el.matches('input[name], textarea[name], select[name]')
            ? el
            : el.closest('input[name], textarea[name], select[name]');

        if (namedControl?.name) {
            const namedSelector = `${namedControl.tagName.toLowerCase()}[name="${CSS.escape(namedControl.name)}"]`;

            if (isUniqueOrContains(namedSelector)) return namedSelector;
        }

        for (const selector of [
            '.fi-modal-slide-over-window',
            '.fi-modal-window',
            '.fi-sidebar-nav',
            '.fi-sidebar',
            '.fi-topbar',
            '.fi-page-header',
        ]) {
            const container = el.closest(selector);

            if (container && isUnique(selector)) return selector;
        }

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
                if (isUniqueOrContains(linkSel)) {
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
