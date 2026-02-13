@php
    $environments = config('floop.environments', ['local', 'staging', 'testing']);
    $shouldRender = in_array('*', $environments) || app()->environment($environments);
    $shouldRender = $shouldRender && app(\IgcLabs\Floop\FloopManager::class)->isEnabled();
@endphp

@if($shouldRender)
@php
    $position = config('floop.position', 'bottom-right');
    $defaultType = config('floop.default_type', 'feedback');
    $shortcut = config('floop.shortcut', 'ctrl+shift+f');
    $hideShortcut = config('floop.hide_shortcut', 'ctrl+shift+h');
    $routePrefix = config('floop.route_prefix', '_feedback');
    $context = $_floopContext ?? [];

    $capturedViews = \IgcLabs\Floop\Http\Middleware\InjectFloopContext::$capturedViews;
    if (!empty($capturedViews)) {
        $context['views'] = $capturedViews;
    }
@endphp

<style>
    #floop-widget {
        --floop-primary: #4b93d6;
        --floop-primary-hover: #3a7ebf;
        --floop-bg: #ffffff;
        --floop-bg-secondary: #f8f9fa;
        --floop-text: #1a1a2e;
        --floop-text-secondary: #6b7280;
        --floop-border: #e5e7eb;
        --floop-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        --floop-badge-bg: #ef4444;
    }

    @media (prefers-color-scheme: dark) {
        #floop-widget {
            --floop-bg: #1e1e2e;
            --floop-bg-secondary: #2a2a3e;
            --floop-text: #e2e8f0;
            --floop-text-secondary: #94a3b8;
            --floop-border: #374151;
            --floop-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
    }

    [data-bs-theme="dark"] #floop-widget {
        --floop-bg: #1e1e2e;
        --floop-bg-secondary: #2a2a3e;
        --floop-text: #e2e8f0;
        --floop-text-secondary: #94a3b8;
        --floop-border: #374151;
        --floop-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    }

    #floop-widget * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    #floop-widget {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        font-size: 13px;
        color: var(--floop-text);
        line-height: 1.5;
        position: fixed;
        z-index: 99999;
        @if(str_contains($position, 'bottom'))
            bottom: 20px;
        @else
            top: 20px;
        @endif
        @if(str_contains($position, 'right'))
            right: 20px;
        @else
            left: 20px;
        @endif
    }

    #floop-widget .floop-trigger {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: none;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        position: relative;
    }

    #floop-widget .floop-trigger:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.35);
    }

    #floop-widget .floop-trigger svg {
        width: 34px;
        height: 34px;
    }

    #floop-widget .floop-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: var(--floop-badge-bg);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        min-width: 18px;
        height: 18px;
        border-radius: 9px;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 0 5px;
        line-height: 1;
    }

    #floop-widget .floop-panel {
        display: none;
        position: absolute;
        width: 320px;
        background: var(--floop-bg);
        border: 1px solid var(--floop-border);
        border-radius: 10px;
        box-shadow: var(--floop-shadow);
        overflow: hidden;
        @if(str_contains($position, 'bottom'))
            bottom: 54px;
        @else
            top: 54px;
        @endif
        @if(str_contains($position, 'right'))
            right: 0;
        @else
            left: 0;
        @endif
    }

    #floop-widget .floop-panel.floop-open {
        display: block;
        animation: floop-slide-in 0.15s ease;
    }

    @keyframes floop-slide-in {
        from { opacity: 0; transform: translateY({{ str_contains($position, 'bottom') ? '8px' : '-8px' }}); }
        to { opacity: 1; transform: translateY(0); }
    }

    #floop-widget .floop-panel-header {
        padding: 12px 14px 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    #floop-widget .floop-panel-header h3 {
        font-size: 14px;
        font-weight: 600;
        color: var(--floop-text);
        margin: 0;
    }

    #floop-widget .floop-close {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--floop-text-secondary);
        font-size: 18px;
        line-height: 1;
        padding: 0 2px;
    }

    #floop-widget .floop-panel-body {
        padding: 12px 14px 14px;
    }

    #floop-widget .floop-message {
        width: 100%;
        min-height: 80px;
        padding: 8px 10px;
        border: 1px solid var(--floop-border);
        border-radius: 6px;
        background: var(--floop-bg-secondary);
        color: var(--floop-text);
        font-family: inherit;
        font-size: 13px;
        resize: vertical;
        margin-bottom: 10px;
        outline: none;
        transition: border-color 0.12s ease;
    }

    #floop-widget .floop-message:focus {
        border-color: var(--floop-primary);
    }

    #floop-widget .floop-message::placeholder {
        color: var(--floop-text-secondary);
    }

    #floop-widget .floop-context-details {
        margin-bottom: 10px;
    }

    #floop-widget .floop-context-details summary {
        font-size: 11px;
        color: var(--floop-text-secondary);
        cursor: pointer;
        user-select: none;
    }

    #floop-widget .floop-context-body {
        margin-top: 6px;
        padding: 8px;
        background: var(--floop-bg-secondary);
        border-radius: 6px;
        font-size: 11px;
        color: var(--floop-text-secondary);
        word-break: break-all;
        max-height: 120px;
        overflow-y: auto;
    }

    #floop-widget .floop-context-body div {
        margin-bottom: 2px;
    }

    #floop-widget .floop-context-body strong {
        color: var(--floop-text);
    }

    #floop-widget .floop-submit {
        width: 100%;
        padding: 8px;
        background: linear-gradient(135deg, #FF3D77 0%, #FFBD33 35%, #33FF57 50%, #3357FF 75%, #5808b9 100%);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        text-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);
        background-blend-mode: darken;
        background-color: rgba(0, 0, 0, 0.25);
        transition: background-color 0.12s ease;
    }

    #floop-widget .floop-submit:hover {
        background-color: rgba(0, 0, 0, 0.1);
    }

    #floop-widget .floop-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    #floop-widget .floop-success {
        display: none;
        padding: 30px 14px;
        text-align: center;
        font-size: 15px;
        color: var(--floop-text);
    }
</style>

<div id="floop-widget">
    <button class="floop-trigger" type="button" title="Submit feedback ({{ $shortcut ? strtoupper(str_replace('+', ' + ', $shortcut)) : '' }})">
        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="floop-rainbow" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#FF3D77"/><stop offset="35%" stop-color="#FFBD33"/><stop offset="50%" stop-color="#33FF57"/><stop offset="75%" stop-color="#3357FF"/><stop offset="100%" stop-color="#5808b9"/></linearGradient><linearGradient id="floop-metal" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" stop-color="#F0F0F0"/><stop offset="100%" stop-color="#B0B0B0"/></linearGradient><filter id="floop-glow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="1.5" result="blur"/><feComposite in="SourceGraphic" in2="blur" operator="over"/></filter><clipPath id="floop-clip"><circle cx="50" cy="50" r="50"/></clipPath></defs><g clip-path="url(#floop-clip)"><rect width="100" height="100" fill="url(#floop-rainbow)"/><circle cx="20" cy="20" r="1.5" fill="white" opacity="0.6"/><circle cx="80" cy="30" r="2" fill="white" opacity="0.4"/><circle cx="30" cy="80" r="1" fill="white" opacity="0.7"/><circle cx="75" cy="75" r="1.5" fill="white" opacity="0.5"/><g transform="translate(50,55)"><ellipse cx="0" cy="25" rx="20" ry="5" fill="black" opacity="0.1"/><rect x="-25" y="-15" width="50" height="35" rx="8" fill="url(#floop-metal)" stroke="#2D3436" stroke-width="1.5"/><rect x="-5" y="0" width="10" height="2" rx="1" fill="#2D3436" opacity="0.2"/><rect x="-20" y="-10" width="40" height="20" rx="4" fill="#2D3436"/><circle cx="-10" cy="0" r="3" fill="#00F2FF" filter="url(#floop-glow)"/><circle cx="10" cy="0" r="3" fill="#00F2FF" filter="url(#floop-glow)"/><path d="M-18-15A18 18 0 0 1 18-15" fill="none" stroke="#2D3436" stroke-width="1.5"/><path d="M-18-15Q0-35 18-15" fill="url(#floop-metal)" stroke="#2D3436" stroke-width="1.5"/><line x1="0" y1="-28" x2="0" y2="-38" stroke="#2D3436" stroke-width="2" stroke-linecap="round"/><circle cx="0" cy="-40" r="3" fill="#FF3D77" filter="url(#floop-glow)"/><path d="M-25 5Q-40 0-35-15" fill="none" stroke="#2D3436" stroke-width="2.5" stroke-linecap="round"/><path d="M25 5Q40 0 35-15" fill="none" stroke="#2D3436" stroke-width="2.5" stroke-linecap="round"/><path d="M-10-25Q0-30 10-25" fill="none" stroke="white" stroke-width="1" opacity="0.5" stroke-linecap="round"/></g></g><circle cx="50" cy="50" r="49" fill="none" stroke="white" stroke-width="2" opacity="0.3"/></svg>
        <span class="floop-badge">0</span>
    </button>

    <div class="floop-panel">
        <div class="floop-panel-header">
            <h3>Feedback</h3>
            <button class="floop-close" type="button">&times;</button>
        </div>
        <div class="floop-panel-body">
            <div class="floop-form">
                <textarea class="floop-message" rows="4" placeholder="Describe what you noticed&hellip;"></textarea>

                <details class="floop-context-details">
                    <summary>Page context</summary>
                    <div class="floop-context-body">
                        @if(!empty($context))
                            @if(!empty($context['url']))<div><strong>URL:</strong> {{ $context['url'] }}</div>@endif
                            @if(!empty($context['route_name']))<div><strong>Route:</strong> {{ $context['route_name'] }}</div>@endif
                            @if(!empty($context['route_action']) && $context['route_action'] !== 'Closure')<div><strong>Controller:</strong> {{ $context['route_action'] }}</div>@endif
                            @if(!empty($context['method']))<div><strong>Method:</strong> {{ $context['method'] }}</div>@endif
                            @if(!empty($context['views']))<div><strong>View:</strong> {{ $context['views'][0] ?? '' }}</div>@endif
                            @if(count($context['views'] ?? []) > 1)<div><strong>Partials:</strong> {{ implode(', ', array_slice($context['views'], 1)) }}</div>@endif
                        @else
                            <div><strong>URL:</strong> <span class="floop-ctx-url"></span></div>
                        @endif
                    </div>
                </details>

                <button class="floop-submit" type="button">Floop It</button>
            </div>

            <div class="floop-success">&#x2705; Flooped!</div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    var prefix = @json('/' . $routePrefix);
    var defaultType = @json($defaultType);
    var shortcutConfig = @json($shortcut);
    var hideShortcutConfig = @json($hideShortcut);
    var serverContext = @json($context ?: null);

    var widget = document.getElementById('floop-widget');
    var trigger = widget.querySelector('.floop-trigger');
    var panel = widget.querySelector('.floop-panel');
    var closeBtn = widget.querySelector('.floop-close');
    var submitBtn = widget.querySelector('.floop-submit');
    var messageEl = widget.querySelector('.floop-message');
    var badge = widget.querySelector('.floop-badge');
    var formEl = widget.querySelector('.floop-form');
    var successEl = widget.querySelector('.floop-success');

    function getCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function playFloop() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(600, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(200, ctx.currentTime + 0.15);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.2);
        } catch(e) {}
    }

    function openPanel() {
        panel.classList.add('floop-open');
        formEl.style.display = '';
        successEl.style.display = 'none';
        messageEl.focus();
        if (!serverContext) {
            var urlSpan = widget.querySelector('.floop-ctx-url');
            if (urlSpan) urlSpan.textContent = window.location.href;
        }
    }

    function closePanel() {
        panel.classList.remove('floop-open');
    }

    function togglePanel() {
        panel.classList.contains('floop-open') ? closePanel() : openPanel();
    }

    trigger.addEventListener('click', togglePanel);
    closeBtn.addEventListener('click', closePanel);

    messageEl.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submitBtn.click();
        }
    });

    submitBtn.addEventListener('click', function() {
        var message = messageEl.value.trim();
        if (!message) {
            messageEl.style.borderColor = '#ef4444';
            messageEl.focus();
            return;
        }
        messageEl.style.borderColor = '';

        submitBtn.disabled = true;
        submitBtn.textContent = 'Flooping\u2026';

        var body = {
            message: message,
            type: defaultType
        };

        if (serverContext) {
            body._route_name = serverContext.route_name || '';
            body._route_action = serverContext.route_action || '';
            body._route_params = serverContext.route_params || {};
            body._query_params = serverContext.query_params || {};
            body._views = serverContext.views || [];
        } else {
            body._query_params = Object.fromEntries(new URLSearchParams(window.location.search));
        }

        body._viewport = window.innerWidth + 'x' + window.innerHeight;

        fetch(prefix, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'X-Feedback-URL': window.location.href,
                'X-Feedback-Method': serverContext ? (serverContext.method || 'GET') : 'GET'
            },
            body: JSON.stringify(body)
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                playFloop();
                formEl.style.display = 'none';
                successEl.style.display = 'block';
                messageEl.value = '';

                setTimeout(function() {
                    closePanel();
                    formEl.style.display = '';
                    successEl.style.display = 'none';
                }, 1500);

                fetchCounts();
            }
        })
        .catch(function(err) {
            console.error('Floop error:', err);
        })
        .finally(function() {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Floop It';
        });
    });

    function fetchCounts() {
        fetch(prefix + '/counts', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var count = data.pending || 0;
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(function() { badge.style.display = 'none'; });
    }

    fetchCounts();

    function parseShortcut(config) {
        var parts = config.toLowerCase().split('+');
        return {
            key: parts.pop(),
            ctrl: parts.indexOf('ctrl') !== -1,
            shift: parts.indexOf('shift') !== -1,
            alt: parts.indexOf('alt') !== -1,
            meta: parts.indexOf('meta') !== -1 || parts.indexOf('cmd') !== -1
        };
    }

    function matchesShortcut(e, sc) {
        return e.key.toLowerCase() === sc.key && e.ctrlKey === sc.ctrl && e.shiftKey === sc.shift && e.altKey === sc.alt && e.metaKey === sc.meta;
    }

    function toggleVisibility() {
        var hidden = widget.style.display === 'none';
        widget.style.display = hidden ? '' : 'none';
        if (!hidden) closePanel();
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && panel.classList.contains('floop-open')) { closePanel(); return; }
        if (hideShortcutConfig && matchesShortcut(e, parseShortcut(hideShortcutConfig))) {
            e.preventDefault();
            toggleVisibility();
            return;
        }
        if (shortcutConfig && matchesShortcut(e, parseShortcut(shortcutConfig))) {
            e.preventDefault();
            togglePanel();
        }
    });

    document.addEventListener('click', function(e) {
        if (panel.classList.contains('floop-open') && !widget.contains(e.target)) closePanel();
    });
})();
</script>
@endif
