import React, { useState, useEffect, useRef } from 'react';
import { useAuth } from '../lib/AuthContext';

const WP_REST_BASE = import.meta.env.VITE_GRAPHQL_URL
  ? import.meta.env.VITE_GRAPHQL_URL.replace(/\/graphql$/, '') + '/wp-json'
  : '/wp-json';

const Messaging = ({ initialPolicyId = null }) => {
  const { user, loading: authLoading } = useAuth();
  // token lives inside the user object, not as a separate context value
  const token = user?.token ?? null;

  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [convId, setConvId] = useState(null);
  const [chatToken, setChatToken] = useState(null);
  const scrollRef = useRef(null);
  const lastIdRef = useRef(0);
  const [historyLoading, setHistoryLoading] = useState(false);

  // 1. Start or Resume Conversation
  useEffect(() => {
    // Wait for AuthContext to finish hydrating from localStorage
    if (authLoading) return;

    if (!token) {
      setLoading(false); // Confirmed not logged in — stop spinner
      return;
    }

    fetch(`${WP_REST_BASE}/maljani-chat/v1/start`, {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({ 
        email: user?.email,
        policy_id: initialPolicyId 
      })
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          setConvId(data.conversation_id);
          setChatToken(data.token);
        }
      })
      .catch(err => console.error('Chat start error:', err))
      .finally(() => setLoading(false));
  }, [authLoading, token, user?.email, initialPolicyId]);

  // 2. Poll Messages — use a ref for lastId so `messages` is not a dependency
  // (adding messages to deps would restart the interval on every new message)
  useEffect(() => {
    if (!convId || !chatToken) return;

    // Critical: Reset lastId when switching conversations
    lastIdRef.current = 0;
    setMessages([]);
    setHistoryLoading(true);

    const poll = () => {
      fetch(`${WP_REST_BASE}/maljani-chat/v1/poll?conversation_id=${convId}&token=${chatToken}&last_id=${lastIdRef.current}`)
        .then(r => r.json())
        .then(data => {
          if (data.success && Array.isArray(data.messages)) {
            if (data.messages.length > 0) {
              setMessages(prev => {
                // Deduplicate: filter out messages that are already in the list (optimistic or previous poll)
                const newMsgs = data.messages.filter(m => !prev.some(p => p.id === m.id));
                if (newMsgs.length === 0) return prev;
                
                const combined = [...prev, ...newMsgs];
                lastIdRef.current = combined[combined.length - 1].id || lastIdRef.current;
                return combined;
              });
            }
            setHistoryLoading(false);
          }
        })
        .catch(err => {
          console.error('Poll error:', err);
          setHistoryLoading(false);
        });
    };

    const interval = setInterval(poll, 4000);
    poll(); // Initial load
    return () => clearInterval(interval);
  }, [convId, chatToken]);

  // 3. Scroll to bottom
  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [messages]);

  const handleSend = async (e) => {
    e.preventDefault();
    if (!input.trim() || sending || !convId) return;

    setSending(true);
    try {
      const res = await fetch(`${WP_REST_BASE}/maljani-chat/v1/message`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}` 
        },
        body: JSON.stringify({
          conversation_id: convId,
          token: chatToken,
          message: input
        })
      });
      const data = await res.json();
      if (data.success) {
        const newMessage = {
          id: data.message_id || Date.now(),
          sender_type: 'user',
          message: input,
          created_at: new Date().toISOString(),
          isOptimistic: true
        };
        setMessages(prev => [...prev, newMessage]);
        setInput('');
      } else {
        alert('Failed to send message: ' + (data.message || 'Unknown error'));
      }
    } catch (err) {
      console.error('Send error:', err);
      alert('Network error while sending message.');
    } finally {
      setSending(false);
    }
  };

  if (loading) return (
    <div style={{ padding: '60px', textAlign: 'center', color: 'var(--slate)' }}>
      <div className="spinner" style={{ margin: '0 auto 20px' }}></div>
      <p>Initializing secure support channel...</p>
    </div>
  );

  if (!token) return (
    <div style={{ padding: '60px', textAlign: 'center', color: 'var(--slate)' }}>
      <div style={{ fontSize: 40, marginBottom: 16 }}>🔒</div>
      <p style={{ fontSize: 16, color: '#fff', marginBottom: 8 }}>Please log in to access Live Support</p>
      <p style={{ fontSize: 13, opacity: 0.7 }}>Sign in with your account to chat with our support team.</p>
    </div>
  );

  return (
    <div className="glass-card" style={{ display: 'flex', flexDirection: 'column', height: '600px', overflow: 'hidden', maxWidth: '900px', margin: '0 auto' }}>
      {/* Header */}
      <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--glass-border)', display: 'flex', alignItems: 'center', gap: 14, background: 'rgba(255,255,255,0.02)' }}>
        <div style={{ width: 12, height: 12, borderRadius: '50%', background: '#22c55e', border: '2px solid rgba(34,197,94,0.3)', boxShadow: '0 0 10px rgba(34,197,94,0.5)' }}></div>
        <div>
          <h3 style={{ fontSize: 16, fontWeight: 700, margin: 0, color: '#fff' }}>Live Support</h3>
          <p style={{ fontSize: 11, color: 'var(--gold)', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.05em', margin: 0 }}>Agency representative is online</p>
        </div>
      </div>

      {/* Messages Area */}
      <div 
        ref={scrollRef}
        style={{ flex: 1, padding: '24px', overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: 16, background: 'rgba(0,0,0,0.1)' }}
      >
        {historyLoading && messages.length === 0 && (
          <div style={{ textAlign: 'center', marginTop: '40px', color: 'var(--slate)' }}>
            <div className="spinner-sm" style={{ margin: '0 auto 12px' }}></div>
            <p style={{ fontSize: 12 }}>Syncing history...</p>
          </div>
        )}

        {!historyLoading && messages.length === 0 && (
          <div style={{ textAlign: 'center', marginTop: '40px', color: 'var(--slate)' }}>
            <div style={{ fontSize: 40, marginBottom: 14 }}>👋</div>
            <p style={{ fontSize: 14 }}>Hello {user?.name}! How can we help you today?</p>
            <p style={{ fontSize: 12, opacity: 0.7 }}>Our team typically responds within a few minutes.</p>
          </div>
        )}

        {messages.map((msg, i) => {
          const isMe = msg.sender_type === 'user';
          return (
            <div key={msg.id || i} style={{ alignSelf: isMe ? 'flex-end' : 'flex-start', maxWidth: '80%', display: 'flex', flexDirection: 'column', alignItems: isMe ? 'flex-end' : 'flex-start' }}>
              <div style={{ 
                padding: '12px 18px', 
                borderRadius: isMe ? '18px 18px 4px 18px' : '18px 18px 18px 4px',
                background: isMe ? 'var(--indigo)' : 'rgba(255,255,255,0.08)',
                border: isMe ? '1px solid rgba(255,255,255,0.1)' : '1px solid var(--glass-border)',
                color: '#fff',
                fontSize: 14,
                lineHeight: 1.5,
                boxShadow: isMe ? '0 4px 12px rgba(49,99,49,0.3)' : 'none'
              }}>
                {msg.message}
              </div>
              <span style={{ fontSize: 10, color: 'var(--slate)', marginTop: 4, padding: '0 4px' }}>
                {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
              </span>
            </div>
          );
        })}
      </div>

      {/* Input Area */}
      <form 
        onSubmit={handleSend}
        style={{ padding: '20px 24px', borderTop: '1px solid var(--glass-border)', background: 'rgba(255,255,255,0.02)', display: 'flex', gap: 12 }}
      >
        <input 
          type="text" 
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Type your message here..."
          style={{ 
            flex: 1, 
            background: 'rgba(0,0,0,0.2)', 
            border: '1px solid var(--glass-border)', 
            borderRadius: '12px', 
            padding: '12px 16px',
            color: '#fff',
            fontSize: 14,
            outline: 'none'
          }}
          disabled={sending}
        />
        <button 
          type="submit"
          className="btn btn--primary"
          style={{ padding: '12px 24px', borderRadius: '12px', minWidth: '100px' }}
          disabled={sending || !input.trim()}
        >
          {sending ? '...' : 'Send'}
        </button>
      </form>
    </div>
  );
};

export default Messaging;
