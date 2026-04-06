import React, { useState, useRef, useEffect } from 'react';
import { useQuery, useMutation } from 'urql';
import { useAuth } from '../lib/AuthContext';
import { useResponsive } from '../lib/useResponsive';

const MY_NOTIFICATIONS = `
  query MyNotifications {
    myNotifications {
      id
      type
      title
      message
      isRead
      policyId
      createdAt
    }
  }
`;

const MARK_READ = `
  mutation MarkRead($ids: [Int]) {
    maljaniMarkNotificationsRead(input: { ids: $ids }) {
      success
      count
    }
  }
`;

const TYPE_META = {
  status_change:    { icon: '🔄', color: '#60a5fa', label: 'Status' },
  cancellation:     { icon: '🚫', color: '#f87171', label: 'Cancelled' },
  cover_ending:     { icon: '⏰', color: '#fbbf24', label: 'Expiring' },
  payment_reminder: { icon: '💳', color: '#f59e0b', label: 'Payment' },
  info_request:     { icon: '📋', color: '#a78bfa', label: 'Info Needed' },
  info:             { icon: 'ℹ️', color: 'var(--slate)', label: 'Info' },
};
const meta = (type) => TYPE_META[type] || TYPE_META.info;

const timeAgo = (iso) => {
  if (!iso) return '';
  const secs = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (secs < 60) return 'just now';
  if (secs < 3600) return `${Math.floor(secs / 60)}m ago`;
  if (secs < 86400) return `${Math.floor(secs / 3600)}h ago`;
  if (secs < 604800) return `${Math.floor(secs / 86400)}d ago`;
  return new Date(iso).toLocaleDateString('en-KE', { day: '2-digit', month: 'short' });
};

const NotificationPanel = ({ onNavigate }) => {
  const { user } = useAuth();
  const { mobile } = useResponsive();
  const [open, setOpen] = useState(false);
  const panelRef = useRef(null);

  const [result, reexecute] = useQuery({ query: MY_NOTIFICATIONS, pause: !user?.token });
  const [, markRead] = useMutation(MARK_READ);

  const items = result.data?.myNotifications || [];
  const unread = items.filter(n => !n.isRead);

  // Close on outside click
  useEffect(() => {
    if (!open) return;
    const handler = (e) => {
      if (panelRef.current && !panelRef.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  const handleOpen = () => {
    setOpen(o => !o);
    reexecute({ requestPolicy: 'network-only' });
  };

  const handleMarkAllRead = async () => {
    await markRead({ ids: [] });
    reexecute({ requestPolicy: 'network-only' });
  };

  const handleItemClick = async (n) => {
    if (!n.isRead) {
      await markRead({ ids: [n.id] });
      reexecute({ requestPolicy: 'network-only' });
    }
    if (n.policyId && onNavigate) {
      onNavigate('policy-detail', n.policyId);
      setOpen(false);
    }
  };

  return (
    <div ref={panelRef} style={{ position: 'relative' }}>
      {/* Bell icon button */}
      <button
        type="button"
        onClick={handleOpen}
        aria-label={`Notifications${unread.length ? ` (${unread.length} unread)` : ''}`}
        style={{
          position: 'relative', background: 'none', border: '1px solid var(--glass-border)',
          borderRadius: 10, padding: '7px 9px', cursor: 'pointer', display: 'flex',
          alignItems: 'center', justifyContent: 'center', transition: 'border-color 0.2s',
        }}
        onMouseEnter={e => { e.currentTarget.style.borderColor = 'rgba(246,166,35,0.4)'; }}
        onMouseLeave={e => { e.currentTarget.style.borderColor = 'var(--glass-border)'; }}
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.7)" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
          <path d="M13.73 21a2 2 0 0 1-3.46 0" />
        </svg>
        {unread.length > 0 && (
          <span style={{
            position: 'absolute', top: -4, right: -4, minWidth: 18, height: 18,
            borderRadius: 9, background: '#ef4444', color: '#fff', fontSize: 10,
            fontWeight: 800, display: 'flex', alignItems: 'center', justifyContent: 'center',
            padding: '0 4px', lineHeight: 1,
          }}>
            {unread.length > 9 ? '9+' : unread.length}
          </span>
        )}
      </button>

      {/* Dropdown panel */}
      {open && (
        <div
          className="fade-in"
          style={{
            position: 'absolute', top: 'calc(100% + 10px)', right: mobile ? -60 : 0,
            width: mobile ? 'calc(100vw - 32px)' : 360, maxWidth: 400,
            maxHeight: 440, overflowY: 'auto',
            background: 'rgba(14,18,42,0.97)', backdropFilter: 'blur(20px)',
            border: '1px solid var(--glass-border-bright)',
            borderRadius: 'var(--radius-lg)', boxShadow: '0 16px 48px rgba(0,0,0,0.5)',
            zIndex: 3000,
          }}
        >
          {/* Header */}
          <div style={{
            padding: '14px 18px', borderBottom: '1px solid var(--glass-border)',
            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          }}>
            <span style={{ fontFamily: 'var(--font-display)', fontSize: 15, fontWeight: 700 }}>Notifications</span>
            {unread.length > 0 && (
              <button type="button" onClick={handleMarkAllRead} style={{
                background: 'none', border: 'none', color: 'var(--gold)', fontSize: 11,
                fontWeight: 700, cursor: 'pointer', padding: 0, fontFamily: 'var(--font-body)',
              }}>
                Mark all read
              </button>
            )}
          </div>

          {/* Items */}
          {result.fetching && (
            <div style={{ padding: '32px 0', textAlign: 'center', color: 'var(--slate)', fontSize: 13 }}>Loading…</div>
          )}

          {!result.fetching && items.length === 0 && (
            <div style={{ padding: '40px 20px', textAlign: 'center' }}>
              <div style={{ fontSize: 32, marginBottom: 10 }}>🔔</div>
              <p style={{ color: 'var(--slate)', fontSize: 13 }}>No notifications yet</p>
              <p style={{ color: 'var(--slate)', fontSize: 11, opacity: 0.6, marginTop: 4 }}>You'll see status changes, payment reminders, and more here.</p>
            </div>
          )}

          {!result.fetching && items.map(n => {
            const m = meta(n.type);
            return (
              <button
                key={n.id}
                type="button"
                onClick={() => handleItemClick(n)}
                style={{
                  display: 'flex', gap: 12, padding: '14px 18px', width: '100%',
                  background: n.isRead ? 'transparent' : 'rgba(246,166,35,0.04)',
                  border: 'none', borderBottom: '1px solid rgba(255,255,255,0.04)',
                  cursor: n.policyId ? 'pointer' : 'default', textAlign: 'left',
                  fontFamily: 'var(--font-body)', transition: 'background 0.15s',
                }}
                onMouseEnter={e => { e.currentTarget.style.background = 'rgba(255,255,255,0.04)'; }}
                onMouseLeave={e => { e.currentTarget.style.background = n.isRead ? 'transparent' : 'rgba(246,166,35,0.04)'; }}
              >
                {/* Icon */}
                <div style={{
                  width: 34, height: 34, borderRadius: 10, flexShrink: 0,
                  background: `${m.color}15`, border: `1px solid ${m.color}30`,
                  display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 16,
                }}>
                  {m.icon}
                </div>
                {/* Content */}
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 3 }}>
                    <span style={{ fontSize: 13, fontWeight: n.isRead ? 500 : 700, color: n.isRead ? 'rgba(255,255,255,0.7)' : '#fff', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                      {n.title}
                    </span>
                    {!n.isRead && <span style={{ width: 6, height: 6, borderRadius: '50%', background: 'var(--gold)', flexShrink: 0 }} />}
                  </div>
                  <p style={{ fontSize: 12, color: 'var(--slate)', margin: 0, lineHeight: 1.5, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
                    {n.message}
                  </p>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 5 }}>
                    <span style={{ fontSize: 10, fontWeight: 700, color: m.color, textTransform: 'uppercase', letterSpacing: '0.05em' }}>{m.label}</span>
                    <span style={{ fontSize: 10, color: 'var(--slate)', opacity: 0.65 }}>{timeAgo(n.createdAt)}</span>
                  </div>
                </div>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default NotificationPanel;
