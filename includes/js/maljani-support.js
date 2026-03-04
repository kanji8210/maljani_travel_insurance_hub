(function(){
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('maljani-support-form');
        if (!form) return;

        form.addEventListener('submit', function(e){
            e.preventDefault();
            var email = document.getElementById('maljani-support-email').value || '';
            var message = document.getElementById('maljani-support-message').value || '';
            var resultEl = document.getElementById('maljani-support-result');

            if (!message.trim()) {
                resultEl.style.display = 'block';
                resultEl.textContent = 'Please enter a message.';
                return;
            }

            fetch(maljaniSupport.rest_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': maljaniSupport.nonce
                },
                body: JSON.stringify({ email: email, message: message })
            }).then(function(resp){ return resp.json(); })
            .then(function(data){
                if (data && data.success) {
                    resultEl.style.display = 'block';
                    resultEl.textContent = 'Message sent — our support team will reply shortly.';
                    // store session id if returned
                    if (data.session_id) {
                        try { localStorage.setItem('maljani_session_id', data.session_id); } catch (e) {}
                        // set cookie for public endpoint
                        document.cookie = 'maljani_session_id=' + data.session_id + '; path=/; max-age=' + (30*24*60*60);
                    }
                    // refresh conversation if modal open
                    loadPublicConversation();
                    form.reset();
                } else {
                    resultEl.style.display = 'block';
                    resultEl.textContent = (data && data.message) ? data.message : 'An error occurred.';
                }
            }).catch(function(){
                resultEl.style.display = 'block';
                resultEl.textContent = 'Network error.';
            });
        });

        // Floating button interactions
        var floatBtn = document.querySelector('.maljani-support-button');
        var modal = document.querySelector('.maljani-support-modal');
        if (floatBtn && modal) {
            floatBtn.addEventListener('click', function(){
                modal.style.display = modal.style.display === 'block' ? 'none' : 'block';
            });

            // Load unread count for admins
            if (typeof maljaniSupport !== 'undefined' && maljaniSupport.is_admin && maljaniSupport.unread_url) {
                var updateUnread = function(){
                    fetch(maljaniSupport.unread_url, { method: 'GET', headers: { 'X-WP-Nonce': maljaniSupport.nonce } })
                        .then(function(r){ return r.json(); })
                        .then(function(d){
                            if (d && d.count !== undefined) {
                                var c = floatBtn.querySelector('.count');
                                if (c) c.textContent = d.count;
                            }
                        }).catch(function(){});
                };
                // initial
                updateUnread();
                // poll every 15 seconds
                setInterval(updateUnread, 15000);
            }
        }
        
        // Conversation handling for public users
        var convContainer = document.getElementById('maljani-public-conversation');
        function getSessionId() {
            try { var s = localStorage.getItem('maljani_session_id'); if (s) return parseInt(s,10); } catch(e) {}
            var m = document.cookie.match(/(^|;)\s*maljani_session_id=([^;]+)/);
            return m ? parseInt(decodeURIComponent(m[2]),10) : 0;
        }

        function renderConversation(messages) {
            if (!convContainer) return;
            convContainer.innerHTML = '';
            if (!messages || !messages.length) { convContainer.style.display='none'; return; }
            messages.forEach(function(m){
                var div = document.createElement('div');
                div.className = 'maljani-message ' + (m.sender === 'agent' ? 'agent' : 'user');
                var who = document.createElement('div'); who.className='meta'; who.textContent = (m.sender === 'agent' ? 'Agent' : (m.email || 'You')) + ' · ' + m.created_at;
                var bubble = document.createElement('div'); bubble.className='bubble'; bubble.innerHTML = (m.message||'').replace(/\n/g,'<br>');
                div.appendChild(who); div.appendChild(bubble); convContainer.appendChild(div);
            });
            convContainer.style.display='block';
            convContainer.scrollTop = convContainer.scrollHeight;
        }

        function loadPublicConversation() {
            var sid = getSessionId();
            if (!sid) return;
            var url = maljaniSupport.rest_url.replace(/send$/, 'session/' + sid + '/public');
            // include email param if provided in form
            var email = document.getElementById('maljani-support-email-float').value || '';
            if (email) url += '?email=' + encodeURIComponent(email);
            fetch(url, { method: 'GET' }).then(function(r){ return r.json(); }).then(function(d){ if (d && d.success && d.messages) renderConversation(d.messages); });
        }

        // When modal opens, load conversation
        var floatBtn = document.querySelector('.maljani-support-button');
        var modalEl = document.getElementById('maljani-support-modal');
        if (floatBtn && modalEl) {
            floatBtn.addEventListener('click', function(){
                if (modalEl.style.display === 'block') return;
                setTimeout(loadPublicConversation, 200);
            });
        }
    });
})();
