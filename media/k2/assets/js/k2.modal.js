/**
 * @version    2.x (rolling release)
 * @package    K2
 * @author     JoomlaWorks https://www.joomlaworks.net
 * @copyright  Copyright (c) 2009 - 2026 JoomlaWorks Ltd. All rights reserved.
 * @license    GNU/GPL: https://gnu.org/licenses/gpl.html
 */

/**
 * K2 - Modal bridge for Joomla! 4/5/6
 *
 * Joomla 4 dropped MooTools and Joomla 6 no longer ships
 * "media/system/js/squeezebox.js", but K2's backend code still calls
 * SqueezeBox.initialize() / SqueezeBox.fromElement() / SqueezeBox.close().
 *
 * This file provides a dependency free drop-in replacement with the same
 * public API, used by the media manager (images, video, attachments),
 * the extra fields image picker and any third party K2 template.
 * It also mirrors itself as "K2Modal" so new code can use a neutral name.
 */
(function (window, document) {
    'use strict';

    // A real SqueezeBox (or a previous run of this file) already exists.
    if (window.SqueezeBox && typeof window.SqueezeBox.fromElement === 'function') {
        if (!window.K2Modal) {
            window.K2Modal = window.SqueezeBox;
        }
        return;
    }

    var defaults = {
        handler: 'iframe',
        size: { x: 900, y: 600 },
        title: '',
        classWindow: 'k2-modal-dialog',
        closeBtnText: '&times;'
    };

    var state = {
        overlay: null,
        dialog: null,
        bar: null,
        title: null,
        closeBtn: null,
        body: null,
        frame: null,
        onClose: null,
        lastFocus: null,
        keyHandler: null,
        open: false
    };

    var stylesInjected = false;

    /**
     * Inject the (tiny) stylesheet once, so no extra HTTP request and no
     * dependency on k2.backend.css being loaded is required.
     */
    function injectStyles() {
        if (stylesInjected || document.getElementById('k2-modal-styles')) {
            return;
        }

        stylesInjected = true;

        var css = ''
            + '.k2-modal{position:fixed;top:0;left:0;right:0;bottom:0;z-index:10050;display:none;}'
            + '.k2-modal.k2-modal-open{display:block;}'
            + '.k2-modal-backdrop{position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);}'
            + '.k2-modal-dialog{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);'
            + 'background:#fff;border-radius:6px;overflow:hidden;display:flex;flex-direction:column;'
            + 'box-shadow:0 12px 40px rgba(0,0,0,.35);max-width:96vw;max-height:94vh;}'
            + '.k2-modal-bar{display:flex;align-items:center;justify-content:space-between;gap:10px;'
            + 'padding:8px 12px;background:#10213e;color:#fff;font-size:15px;line-height:1.4;flex:0 0 auto;}'
            + '.k2-modal-title{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:400;}'
            + '.k2-modal-close{background:transparent;border:0;color:#fff;font-size:24px;line-height:1;'
            + 'cursor:pointer;padding:0 4px;opacity:.75;}'
            + '.k2-modal-close:hover,.k2-modal-close:focus{opacity:1;outline:none;}'
            + '.k2-modal-body{flex:1 1 auto;position:relative;background:#fff;min-height:0;}'
            + '.k2-modal-body iframe{display:block;width:100%;height:100%;border:0;}'
            + 'body.k2-modal-locked{overflow:hidden;}';

        var style = document.createElement('style');
        style.type = 'text/css';
        style.id = 'k2-modal-styles';

        if (style.styleSheet) {
            style.styleSheet.cssText = css;
        } else {
            style.appendChild(document.createTextNode(css));
        }

        (document.head || document.getElementsByTagName('head')[0] || document.body).appendChild(style);
    }

    /**
     * Build the modal DOM lazily - only when a modal is actually requested.
     */
    function build() {
        if (state.overlay) {
            return;
        }

        injectStyles();

        var overlay = document.createElement('div');
        overlay.className = 'k2-modal';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-hidden', 'true');

        var backdrop = document.createElement('div');
        backdrop.className = 'k2-modal-backdrop';

        var dialog = document.createElement('div');
        dialog.className = defaults.classWindow;

        var bar = document.createElement('div');
        bar.className = 'k2-modal-bar';

        var title = document.createElement('span');
        title.className = 'k2-modal-title';

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'k2-modal-close';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.innerHTML = defaults.closeBtnText;

        var body = document.createElement('div');
        body.className = 'k2-modal-body';

        var frame = document.createElement('iframe');
        frame.setAttribute('frameborder', '0');
        frame.setAttribute('allowfullscreen', 'allowfullscreen');
        frame.setAttribute('title', 'K2');

        bar.appendChild(title);
        bar.appendChild(closeBtn);
        body.appendChild(frame);
        dialog.appendChild(bar);
        dialog.appendChild(body);
        overlay.appendChild(backdrop);
        overlay.appendChild(dialog);

        (document.body || document.documentElement).appendChild(overlay);

        // Close on backdrop click and on the close button
        backdrop.addEventListener('click', function () {
            api.close();
        });
        closeBtn.addEventListener('click', function () {
            api.close();
        });

        state.overlay = overlay;
        state.dialog = dialog;
        state.bar = bar;
        state.title = title;
        state.closeBtn = closeBtn;
        state.body = body;
        state.frame = frame;
    }

    function applySize(size) {
        var x = parseInt(size && size.x ? size.x : defaults.size.x, 10);
        var y = parseInt(size && size.y ? size.y : defaults.size.y, 10);

        if (isNaN(x) || x <= 0) {
            x = defaults.size.x;
        }
        if (isNaN(y) || y <= 0) {
            y = defaults.size.y;
        }

        // Never exceed the viewport
        var maxX = window.innerWidth ? window.innerWidth - 20 : x;
        var maxY = window.innerHeight ? window.innerHeight - 20 : y;

        if (x > maxX) {
            x = maxX;
        }
        if (y > maxY) {
            y = maxY;
        }

        state.dialog.style.width = x + 'px';
        state.dialog.style.height = y + 'px';
    }

    function show() {
        state.overlay.classList.add('k2-modal-open');
        state.overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('k2-modal-locked');
        state.open = true;

        state.lastFocus = document.activeElement;

        try {
            state.closeBtn.focus();
        } catch (e) {
            // Ignore focus errors in old browsers
        }

        state.keyHandler = function (event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                api.close();
            }
        };

        document.addEventListener('keydown', state.keyHandler, false);
    }

    function hide() {
        if (!state.overlay) {
            return;
        }

        state.overlay.classList.remove('k2-modal-open');
        state.overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('k2-modal-locked');
        state.open = false;

        if (state.keyHandler) {
            document.removeEventListener('keydown', state.keyHandler, false);
            state.keyHandler = null;
        }

        // Stop any running media/video/audio inside the frame
        if (state.frame) {
            state.frame.src = 'about:blank';
        }

        try {
            if (state.lastFocus && state.lastFocus.focus) {
                state.lastFocus.focus();
            }
        } catch (e) {
            // Ignore focus errors
        }

        state.lastFocus = null;
    }

    function normalizeOptions(options) {
        options = options || {};

        // SqueezeBox accepted both "size: {x, y}" and a flat "width/height"
        if (!options.size) {
            if (options.width || options.height) {
                options.size = {
                    x: options.width || defaults.size.x,
                    y: options.height || defaults.size.y
                };
            } else {
                options.size = defaults.size;
            }
        }

        return options;
    }

    var api = {
        version: '2.28',

        /**
         * SqueezeBox.initialize(options) - merge global options.
         */
        initialize: function (options) {
            options = options || {};
            build();

            if (options.size) {
                defaults.size = {
                    x: options.size.x || defaults.size.x,
                    y: options.size.y || defaults.size.y
                };
            }
            if (options.title) {
                defaults.title = options.title;
            }
            if (options.handler) {
                defaults.handler = options.handler;
            }

            return this;
        },

        /**
         * SqueezeBox.fromElement(element, options)
         * K2 passes { handler: 'iframe', url: '...', size: {x, y}, onClose: fn }
         */
        fromElement: function (element, options) {
            options = normalizeOptions(options);

            var url = options.url || (element && element.getAttribute ? element.getAttribute('href') : null);

            return api.open(url, options);
        },

        /**
         * SqueezeBox.open(url, options)
         */
        open: function (url, options) {
            options = normalizeOptions(options);
            build();

            state.onClose = (typeof options.onClose === 'function') ? options.onClose : null;

            state.title.textContent = options.title || defaults.title || '';

            if (options.title) {
                state.bar.style.display = '';
            } else {
                // Keep the bar - it also carries the close button
                state.bar.style.display = '';
            }

            applySize(options.size);

            // handler is always an iframe here: K2 only embeds component views
            state.frame.src = url || 'about:blank';

            show();

            return this;
        },

        /**
         * SqueezeBox.close()
         */
        close: function () {
            var callback = state.onClose;
            state.onClose = null;

            hide();

            if (typeof callback === 'function') {
                try {
                    callback();
                } catch (e) {
                    // Never let a template callback break the close chain
                }
            }

            return this;
        },

        isOpen: function () {
            return !!state.open;
        },

        /**
         * Small helper for backend views: set the value of a field in the
         * opener document and close the modal (mirrors elFinderUpdate()).
         */
        applyValue: function (fieldID, value) {
            var target = window.parent && window.parent.document ? window.parent.document : document;
            var field = target.getElementById(fieldID);

            if (field) {
                field.value = value;

                // Fire the events 3rd party code may listen to
                try {
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                } catch (e) {
                    // Older browsers: ignore
                }
            }

            if (window.parent && window.parent.K2Modal) {
                window.parent.K2Modal.close();
            } else {
                api.close();
            }
        }
    };

    window.SqueezeBox = api;
    window.K2Modal = api;

    // Convenience global used by the media manager view
    if (typeof window.k2ModalClose !== 'function') {
        window.k2ModalClose = function () {
            api.close();
        };
    }
}(window, document));
