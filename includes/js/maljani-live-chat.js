jQuery(document).ready(function($) {
    var chatWidget = $('#maljani-live-chat-widget');
    var chatBody = $('#maljani-chat-body');
    var chatMessages = $('#maljani-chat-messages');
    var btnToggle = $('#maljani-chat-toggle');
    var headerToggle = $('#maljani-chat-header');
    
    var convId = localStorage.getItem('maljani_chat_conv_id') || 0;
    var token = localStorage.getItem('maljani_chat_token') || '';
    var lastId = 0;
    var pollInterval;

    // Expand initially if we have an active session
    if (convId && token) {
        // optionally keep it closed but initialize state
        btnToggle.text('+');
    } else {
        btnToggle.text('+');
    }

    // Toggle chat
    headerToggle.click(function() {
        if(chatWidget.hasClass('maljani-chat-closed')) {
            chatWidget.removeClass('maljani-chat-closed');
            chatBody.show();
            btnToggle.text('-');
            if (convId && token) {
                $('#maljani-chat-start-form').hide();
                $('#maljani-chat-input-area').show();
                fetchMessages();
                if (!pollInterval) pollInterval = setInterval(fetchMessages, 4000);
            }
            scrollToBottom();
        } else {
            chatWidget.addClass('maljani-chat-closed');
            chatBody.hide();
            btnToggle.text('+');
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }
    });

    function scrollToBottom() {
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
    }

    function renderMessage(m) {
        if ($('.maljani-msg-' + m.id).length) return; // already rendered
        var type = m.sender_type === 'user' ? 'user' : 'agent';
        var html = '<div class="maljani-chat-msg ' + type + ' maljani-msg-' + m.id + '">';
        html += '<div class="bubble">' + $('<div>').text(m.message).html().replace(/\n/g, '<br/>') + '</div>';
        html += '<span class="time">' + m.created_at + '</span>';
        html += '</div>';
        chatMessages.append(html);
        lastId = m.id;
    }

    function fetchMessages() {
        $.get(maljaniChatParams.rest_url + '/poll', {
            conversation_id: convId, token: token, last_id: lastId
        }, function(res) {
            if(res.success && res.messages.length > 0) {
                var wasAtBottom = chatMessages.scrollTop() + chatMessages.innerHeight() >= chatMessages[0].scrollHeight - 20;
                res.messages.forEach(renderMessage);
                if (wasAtBottom) scrollToBottom();
            }
        });
    }

    // Start chat
    $('#maljani-chat-start-btn').click(function() {
        var email = $('#maljani-chat-email').val();
        if(!email) return alert('Please enter your email.');
        // Disable start btn
        $(this).prop('disabled', true);
        
        $.post(maljaniChatParams.rest_url + '/start', { email: email }, function(res) {
            if (res.success) {
                convId = res.conversation_id;
                token = res.token;
                localStorage.setItem('maljani_chat_conv_id', convId);
                localStorage.setItem('maljani_chat_token', token);
                
                $('#maljani-chat-start-form').hide();
                $('#maljani-chat-input-area').show();
                fetchMessages();
                if (!pollInterval) pollInterval = setInterval(fetchMessages, 4000);
            } else {
                alert(res.message);
                $('#maljani-chat-start-btn').prop('disabled', false);
            }
        });
    });

    // Send message
    $('#maljani-chat-send-btn').click(function() {
        sendMessage();
    });

    $('#maljani-chat-input').keypress(function(e) {
        if(e.which == 13 && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    function sendMessage() {
        var msg = $('#maljani-chat-input').val().trim();
        if(!msg) return;
        
        // Optimistic UI
        var tempMsg = {
            id: 'temp-' + Date.now(),
            sender_type: 'user',
            message: msg,
            created_at: 'Sending...'
        };
        renderMessage(tempMsg);
        scrollToBottom();
        $('#maljani-chat-input').val('');
        
        $.post(maljaniChatParams.rest_url + '/message', {
            conversation_id: convId, token: token, message: msg,
            _wpnonce: maljaniChatParams.nonce
        }, function(res) {
            if (res.success) {
                // remove temp message
                $('.maljani-msg-' + tempMsg.id).remove();
                fetchMessages();
            }
        }).fail(function() {
            $('.maljani-msg-' + tempMsg.id + ' .time').text('Failed to send');
        });
    }
});
