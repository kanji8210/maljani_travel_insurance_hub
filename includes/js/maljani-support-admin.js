(function(){
    'use strict';
    function updateBadge(count) {
        var sel = document.querySelector('#toplevel_page_maljani-support .wp-menu-name');
        if (!sel) return;
        // remove existing badge
        var existing = sel.querySelector('.maljani-admin-badge');
        if (existing) existing.remove();
        if (count > 0) {
            var span = document.createElement('span');
            span.className = 'maljani-admin-badge';
            span.textContent = count;
            sel.appendChild(span);
        }
    }

    function fetchCount() {
        if (typeof maljaniSupportAdmin === 'undefined') return;
        fetch(maljaniSupportAdmin.unread_url, { method: 'GET', headers: {'X-WP-Nonce': maljaniSupportAdmin.nonce }})
            .then(function(r){ return r.json(); })
            .then(function(d){ if (d && d.count !== undefined) updateBadge(d.count); })
            .catch(function(){});
    }

    // Conversation polling when viewing a session
    var lastMessageId = 0;
    function getSessionIdFromUrl() {
        var m = location.search.match(/[?&]session_id=(\d+)/);
        return m ? parseInt(m[1], 10) : 0;
    }

    function fetchNewMessages() {
        if (typeof maljaniSupportAdmin === 'undefined') return;
        var sessionId = getSessionIdFromUrl();
        if (!sessionId) return;
        var url = maljaniSupportAdmin.session_url_base + sessionId;
        if (lastMessageId > 0) url += '?after_id=' + lastMessageId;
        fetch(url, { method: 'GET', headers: {'X-WP-Nonce': maljaniSupportAdmin.nonce }})
            .then(function(r){ return r.json(); })
            .then(function(d){ if (d && d.messages && d.messages.length) appendMessages(d.messages); })
            .catch(function(){});
    }

    function appendMessages(msgs) {
        var conv = document.querySelector('.maljani-conversation');
        if (!conv) return;
        msgs.forEach(function(m){
            // skip if message already exists
            if (conv.querySelector('[data-message-id="' + m.id + '"]')) return;
            var div = document.createElement('div');
            div.className = 'maljani-message ' + (m.sender === 'agent' ? 'agent' : 'user');
            div.setAttribute('data-message-id', m.id);
            var meta = document.createElement('div'); meta.className = 'meta';
            var who = document.createElement('strong'); who.textContent = (m.sender === 'agent' ? 'Agent' : (m.email || 'User'));
            var time = document.createElement('span'); time.className = 'time'; time.style.color='#666'; time.style.marginLeft='8px'; time.textContent = m.created_at;
            meta.appendChild(who); meta.appendChild(time);
            var bubble = document.createElement('div'); bubble.className = 'bubble'; bubble.style.marginTop='6px'; bubble.style.padding='8px'; bubble.style.background='#f7f7f7'; bubble.style.borderRadius='4px'; bubble.style.maxWidth='80%'; bubble.innerHTML = escapeHtml(m.message).replace(/\n/g, '<br>');
            div.appendChild(meta); div.appendChild(bubble);
            conv.appendChild(div);
            lastMessageId = Math.max(lastMessageId, parseInt(m.id,10));
        });
        // scroll to bottom
        conv.scrollTop = conv.scrollHeight;
    }

    // Optimistic reply: intercept admin reply form
    function initOptimisticReply() {
        var form = document.querySelector('form[action*="maljani_support_reply"]');
        if (!form) return;
        form.addEventListener('submit', function(e){
            // if JS should handle (we have REST), prevent default and post via REST
            var sessionIdInput = form.querySelector('input[name="session_id"]');
            if (!sessionIdInput) return; // fallback to normal POST
            e.preventDefault();
            var sessionId = parseInt(sessionIdInput.value,10);
            var textarea = form.querySelector('textarea[name="message"]');
            if (!textarea) return;
            var text = textarea.value.trim();
            if (!text) return;

            // append optimistic message
            var conv = document.querySelector('.maljani-conversation');
            var tmpId = 'tmp-' + Date.now();
            var div = document.createElement('div');
            div.className = 'maljani-message agent pending';
            div.setAttribute('data-message-id', tmpId);
            var meta = document.createElement('div'); meta.className = 'meta';
            var who = document.createElement('strong'); who.textContent = 'Agent';
            var time = document.createElement('span'); time.className = 'time'; time.style.color='#666'; time.style.marginLeft='8px'; time.textContent = (new Date()).toLocaleString();
            meta.appendChild(who); meta.appendChild(time);
            var bubble = document.createElement('div'); bubble.className = 'bubble'; bubble.style.marginTop='6px'; bubble.style.padding='8px'; bubble.style.background='#d9f7d9'; bubble.style.borderRadius='4px'; bubble.style.maxWidth='80%'; bubble.textContent = text;
            div.appendChild(meta); div.appendChild(bubble);
            if (conv) conv.appendChild(div);
            if (conv) conv.scrollTop = conv.scrollHeight;

            // clear textarea optimistically
            textarea.value = '';

            // send to REST endpoint
            var url = maljaniSupportAdmin.session_message_url_base + sessionId + '/message';
            fetch(url, { method: 'POST', headers: { 'Content-Type':'application/json', 'X-WP-Nonce': maljaniSupportAdmin.nonce }, body: JSON.stringify({ message: text }) })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (d && d.success && d.message && d.message.id) {
                        // replace tmp id with real id
                        var newId = d.message.id;
                        var node = conv.querySelector('[data-message-id="' + tmpId + '"]');
                        if (node) node.setAttribute('data-message-id', newId);
                        lastMessageId = Math.max(lastMessageId, parseInt(newId,10));
                    } else {
                        // mark as failed
                        var node = conv.querySelector('[data-message-id="' + tmpId + '"]');
                        if (node) {
                            node.classList.add('failed');
                            attachRetry(node, text, sessionId);
                        }
                    }
                }).catch(function(){
                    var node = conv.querySelector('[data-message-id="' + tmpId + '"]');
                    if (node) {
                        node.classList.add('failed');
                        attachRetry(node, text, sessionId);
                    }
                }).finally(function(){
                    // ensure retry UI also attaches to any pre-existing failed items
                    initRetryButtons();
                });
        });
    }

    function attachRetry(node, messageText, sessionId) {
        if (!node) return;
        if (node.querySelector('.maljani-retry')) return; // already attached
        var btn = document.createElement('button');
        btn.className = 'maljani-retry';
        btn.textContent = 'Retry';
        btn.style.marginLeft = '8px';
        btn.addEventListener('click', function(e){
            e.preventDefault();
            // remove failed class and mark pending
            node.classList.remove('failed');
            node.classList.add('pending');
            // send again
            var url = maljaniSupportAdmin.session_message_url_base + sessionId + '/message';
            fetch(url, { method: 'POST', headers: { 'Content-Type':'application/json', 'X-WP-Nonce': maljaniSupportAdmin.nonce }, body: JSON.stringify({ message: messageText }) })
                .then(function(r){ return r.json(); })
                .then(function(d){ if (d && d.success && d.message && d.message.id) { node.setAttribute('data-message-id', d.message.id); node.classList.remove('pending'); } else { node.classList.add('failed'); } })
                .catch(function(){ node.classList.add('failed'); });
        });
        var meta = node.querySelector('.meta');
        if (meta) meta.appendChild(btn);
    }

    function initRetryButtons() {
        var conv = document.querySelector('.maljani-conversation');
        if (!conv) return;
        var failed = conv.querySelectorAll('.maljani-message.failed');
        var sessionId = getSessionIdFromUrl();
        failed.forEach(function(n){
            var bubble = n.querySelector('.bubble');
            var text = bubble ? bubble.textContent : '';
            attachRetry(n, text, sessionId);
        });
    }

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m){ return map[m]; });
    }

    document.addEventListener('DOMContentLoaded', function(){
        fetchCount();
        setInterval(fetchCount, 30000);
        // if viewing a session, initialize lastMessageId from DOM and poll
        var sessionId = getSessionIdFromUrl();
        if (sessionId) {
            var conv = document.querySelector('.maljani-conversation');
            if (conv) {
                var msgs = conv.querySelectorAll('[data-message-id]');
                msgs.forEach(function(n){ lastMessageId = Math.max(lastMessageId, parseInt(n.getAttribute('data-message-id'),10)); });
                conv.scrollTop = conv.scrollHeight;
            }
            // poll more frequently for live feel
            fetchNewMessages();
            setInterval(fetchNewMessages, 5000);
            initOptimisticReply();
            // attempt websocket connection
            if (typeof maljaniSupportAdmin !== 'undefined' && maljaniSupportAdmin.ws_url) {
                try {
                    var ws = new WebSocket(maljaniSupportAdmin.ws_url);
                    ws.addEventListener('open', function(){
                        console.log('WS connected');
                    });
                    ws.addEventListener('message', function(ev){
                        try {
                            var data = JSON.parse(ev.data);
                            if (data && data.type === 'message' && parseInt(data.session_id,10) === sessionId) {
                                appendMessages([data]);
                            }
                        } catch (e) { }
                    });
                    ws.addEventListener('close', function(){ console.log('WS closed, keeping polling'); });
                    ws.addEventListener('error', function(){ console.log('WS error, keeping polling'); });
                } catch (e) {
                    console.log('WS init failed, using polling');
                }
            }
        }
    });
})();
