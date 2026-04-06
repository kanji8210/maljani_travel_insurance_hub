import React, { useEffect, useState, useRef, useCallback } from 'react';
import { useAuth } from '../lib/AuthContext';

const Header = ({ onNavigate, activeView, canGoBack, canGoForward, onBack, onForward }) => {
  const { user, role, logout, loading } = useAuth();
  const [scrolled, setScrolled] = useState(false);
  const [compact, setCompact] = useState(window.innerWidth <= 1024);
  const [mobile, setMobile] = useState(window.innerWidth <= 768);
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const dropdownRef = useRef(null);
  const menuRef = useRef(null);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 40);
    const onResize = () => {
      setCompact(window.innerWidth <= 1024);
      setMobile(window.innerWidth <= 768);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onResize);
    return () => {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onResize);
    };
  }, []);

  // Close dropdown / mobile menu on outside click
  useEffect(() => {
    const handler = (e) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
        setDropdownOpen(false);
      }
      if (menuRef.current && !menuRef.current.contains(e.target)) {
        setMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    document.addEventListener('touchstart', handler);
    return () => {
      document.removeEventListener('mousedown', handler);
      document.removeEventListener('touchstart', handler);
    };
  }, []);

  // Close mobile menu on resize to non-mobile
  useEffect(() => {
    if (!mobile) setMenuOpen(false);
  }, [mobile]);

  const navLinks = React.useMemo(() => {
    const links = [
      { label: 'Travel Policies', view: 'catalog' },
    ];

    if (user) {
      links.push({ label: 'Dashboard', view: 'dashboard' });
    }

    links.push({ label: 'Verify Policy', view: 'verify' });
    links.push({ label: 'About', view: 'about' });

    if (!user) {
      links.splice(1, 0, { label: 'For Agencies', view: 'agencies' });
    }

    return links;
  }, [role, user]);

  const handleDropdownToggle = useCallback(() => {
    setDropdownOpen((prev) => !prev);
  }, []);

  return (
    <nav
      className="tic-header"
      style={{
        position: 'fixed', top: 0, left: 0, right: 0, zIndex: 100,
        padding: compact ? '14px 0' : '20px 0',
        transition: 'background 0.35s, backdrop-filter 0.35s, border-color 0.35s',
        background: scrolled ? 'rgba(8,14,39,0.88)' : 'transparent',
        backdropFilter: scrolled ? 'blur(24px)' : 'none',
        borderBottom: scrolled ? '1px solid rgba(255,255,255,0.10)' : '1px solid transparent',
      }}
      role="navigation" aria-label="Main navigation"
    >
      <div className="container">
        <div className="tic-header__inner">

          {/* Row 1: Back/Forward + Logo + Actions */}
          <div className="tic-header__row1">
            {/* Back / Forward + Logo */}
            <div style={{ display: 'flex', alignItems: 'center', gap: mobile ? 6 : 10 }}>
              <button
                onClick={onBack}
                disabled={!canGoBack}
                aria-label="Go back"
                className="tic-header__arrow"
                style={{
                  background: 'none', border: '1px solid',
                  borderColor: canGoBack ? 'rgba(255,255,255,0.18)' : 'rgba(255,255,255,0.07)',
                  borderRadius: 8, width: mobile ? 28 : 32, height: mobile ? 28 : 32,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  cursor: canGoBack ? 'pointer' : 'default',
                  color: canGoBack ? 'rgba(255,255,255,0.65)' : 'rgba(255,255,255,0.2)',
                  fontSize: mobile ? 14 : 16, lineHeight: 1, transition: 'all 0.2s', flexShrink: 0,
                }}
              >←</button>
              <button
                onClick={onForward}
                disabled={!canGoForward}
                aria-label="Go forward"
                className="tic-header__arrow"
                style={{
                  background: 'none', border: '1px solid',
                  borderColor: canGoForward ? 'rgba(255,255,255,0.18)' : 'rgba(255,255,255,0.07)',
                  borderRadius: 8, width: mobile ? 28 : 32, height: mobile ? 28 : 32,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  cursor: canGoForward ? 'pointer' : 'default',
                  color: canGoForward ? 'rgba(255,255,255,0.65)' : 'rgba(255,255,255,0.2)',
                  fontSize: mobile ? 14 : 16, lineHeight: 1, transition: 'all 0.2s', flexShrink: 0,
                }}
              >→</button>

              {/* Logo */}
              <button
                onClick={() => onNavigate('landing')}
                style={{ display: 'flex', alignItems: 'center', gap: 10, background: 'none', border: 'none', cursor: 'pointer', textDecoration: 'none', padding: 0, marginLeft: 4 }}
                aria-label="Travel Insurance Center-Kenya Home"
              >
                <img
                  src="https://mtj.ivk.mybluehost.me/website_e48ea083/wp-content/uploads/2026/03/logo-type.png"
                  alt="TIC-Kenya"
                  style={{ width: mobile ? 32 : 38, height: mobile ? 32 : 38, objectFit: 'contain', borderRadius: 10, flexShrink: 0 }}
                />
                <span style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: mobile ? 15 : 18, color: '#fff', lineHeight: 1.1 }}>
                  TIC<span style={{ color: 'var(--gold)' }}>-Kenya</span>
                </span>
              </button>
            </div>

            {/* Desktop-only: Nav links inline */}
            {!compact && (
              <ul className="tic-header__nav" style={{ display: 'flex', alignItems: 'center', gap: 36, listStyle: 'none', margin: 0, padding: 0 }}>
                {navLinks.map((item) => {
                  const isActive = activeView === item.view;
                  return (
                    <li key={item.view}>
                      <button
                        className="nav-link-luxury"
                        onClick={() => onNavigate(item.view)}
                        aria-current={isActive ? 'page' : undefined}
                        style={{
                          background: 'none', border: 'none', cursor: 'pointer',
                          color: isActive ? '#fff' : 'rgba(255,255,255,0.65)',
                          fontSize: 14, fontWeight: isActive ? 600 : 500,
                          fontFamily: 'var(--font-body)', padding: '4px 0',
                          borderBottom: isActive ? '2px solid var(--gold)' : '2px solid transparent',
                          transition: 'color 0.2s, border-color 0.2s',
                        }}
                      >
                        {item.label}
                      </button>
                    </li>
                  );
                })}
              </ul>
            )}

            {/* Actions area */}
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
              {/* Mobile hamburger */}
              {mobile && (
                <div ref={menuRef} style={{ position: 'relative' }}>
                  <button
                    onClick={() => setMenuOpen((prev) => !prev)}
                    aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                    aria-expanded={menuOpen}
                    style={{
                      background: 'none', border: '1px solid rgba(255,255,255,0.15)',
                      borderRadius: 8, width: 36, height: 36,
                      display: 'flex', alignItems: 'center', justifyContent: 'center',
                      cursor: 'pointer', color: 'rgba(255,255,255,0.75)',
                      transition: 'all 0.25s ease', flexShrink: 0,
                    }}
                  >
                    {menuOpen ? (
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                      </svg>
                    ) : (
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <line x1="3" y1="6" x2="21" y2="6" />
                        <line x1="3" y1="12" x2="21" y2="12" />
                        <line x1="3" y1="18" x2="21" y2="18" />
                      </svg>
                    )}
                  </button>

                  {/* Mobile nav dropdown */}
                  {menuOpen && (
                    <div
                      style={{
                        position: 'absolute', top: 'calc(100% + 12px)', right: 0,
                        background: 'rgba(12,18,44,0.96)',
                        backdropFilter: 'blur(20px)',
                        border: '1px solid rgba(255,255,255,0.12)',
                        borderRadius: 12, padding: '8px 0',
                        minWidth: 200, boxShadow: '0 16px 48px rgba(0,0,0,0.4)',
                        animation: 'ticDropIn 0.2s ease',
                      }}
                    >
                      {navLinks.map((item) => {
                        const isActive = activeView === item.view;
                        return (
                          <button
                            key={item.view}
                            onClick={() => { onNavigate(item.view); setMenuOpen(false); }}
                            aria-current={isActive ? 'page' : undefined}
                            style={{
                              display: 'block', width: '100%', padding: '11px 18px',
                              background: isActive ? 'rgba(255,255,255,0.06)' : 'none',
                              border: 'none', textAlign: 'left',
                              color: isActive ? '#fff' : 'rgba(255,255,255,0.7)',
                              fontSize: 14, fontWeight: isActive ? 600 : 400,
                              fontFamily: 'var(--font-body)',
                              cursor: 'pointer', transition: 'background 0.15s, color 0.15s',
                              borderLeft: isActive ? '2px solid var(--gold)' : '2px solid transparent',
                            }}
                            onMouseEnter={(e) => { if (!isActive) { e.currentTarget.style.background = 'rgba(255,255,255,0.04)'; e.currentTarget.style.color = '#fff'; } }}
                            onMouseLeave={(e) => { if (!isActive) { e.currentTarget.style.background = 'none'; e.currentTarget.style.color = 'rgba(255,255,255,0.7)'; } }}
                          >
                            {item.label}
                          </button>
                        );
                      })}
                    </div>
                  )}
                </div>
              )}
              {loading ? (
                <div style={{ width: compact ? 36 : 100, height: 32, background: 'rgba(255,255,255,0.05)', borderRadius: 8 }} />
              ) : compact ? (
                /* Tablet + Mobile: User initial icon with dropdown */
                <div
                  ref={dropdownRef}
                  style={{ position: 'relative' }}
                >
                  <button
                    onClick={handleDropdownToggle}
                    aria-label="User menu"
                    aria-expanded={dropdownOpen}
                    style={{
                      width: 38, height: 38, borderRadius: '50%',
                      background: user ? 'var(--gold, #d4a053)' : 'rgba(255,255,255,0.12)',
                      border: '2px solid rgba(255,255,255,0.2)',
                      color: user ? '#0a0e1a' : 'rgba(255,255,255,0.6)',
                      fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 16,
                      cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center',
                      transition: 'all 0.25s ease',
                    }}
                  >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                      <circle cx="12" cy="7" r="4" />
                    </svg>
                  </button>

                  {/* Dropdown */}
                  {dropdownOpen && (
                    <div
                      style={{
                        position: 'absolute', top: 'calc(100% + 10px)', right: 0,
                        background: 'rgba(12,18,44,0.96)',
                        backdropFilter: 'blur(20px)',
                        border: '1px solid rgba(255,255,255,0.12)',
                        borderRadius: 12, padding: '8px 0',
                        minWidth: 200, boxShadow: '0 16px 48px rgba(0,0,0,0.4)',
                        animation: 'ticDropIn 0.2s ease',
                      }}
                    >
                      {user ? (
                        <>
                          <div style={{ padding: '12px 18px', borderBottom: '1px solid rgba(255,255,255,0.08)' }}>
                            <div style={{ fontSize: 13, color: 'rgba(255,255,255,0.5)', marginBottom: 4 }}>Signed in as</div>
                            <div style={{ fontSize: 15, fontWeight: 600, color: '#fff' }}>{user.name}</div>
                          </div>
                          <button
                            onClick={() => { onNavigate('dashboard'); setDropdownOpen(false); }}
                            style={{
                              display: 'block', width: '100%', padding: '11px 18px',
                              background: 'none', border: 'none', textAlign: 'left',
                              color: 'rgba(255,255,255,0.75)', fontSize: 14,
                              cursor: 'pointer', transition: 'background 0.15s, color 0.15s',
                            }}
                            onMouseEnter={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.06)'; e.currentTarget.style.color = '#fff'; }}
                            onMouseLeave={(e) => { e.currentTarget.style.background = 'none'; e.currentTarget.style.color = 'rgba(255,255,255,0.75)'; }}
                          >Dashboard</button>
                          <button
                            onClick={() => { logout(); setDropdownOpen(false); }}
                            style={{
                              display: 'block', width: '100%', padding: '11px 18px',
                              background: 'none', border: 'none', textAlign: 'left',
                              color: '#f87171', fontSize: 14,
                              cursor: 'pointer', transition: 'background 0.15s',
                            }}
                            onMouseEnter={(e) => { e.currentTarget.style.background = 'rgba(248,113,113,0.08)'; }}
                            onMouseLeave={(e) => { e.currentTarget.style.background = 'none'; }}
                          >Logout</button>
                        </>
                      ) : (
                        <>
                          <button
                            onClick={() => { onNavigate('login'); setDropdownOpen(false); }}
                            style={{
                              display: 'block', width: '100%', padding: '11px 18px',
                              background: 'none', border: 'none', textAlign: 'left',
                              color: 'rgba(255,255,255,0.75)', fontSize: 14,
                              cursor: 'pointer', transition: 'background 0.15s, color 0.15s',
                            }}
                            onMouseEnter={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.06)'; e.currentTarget.style.color = '#fff'; }}
                            onMouseLeave={(e) => { e.currentTarget.style.background = 'none'; e.currentTarget.style.color = 'rgba(255,255,255,0.75)'; }}
                          >Login</button>
                          <button
                            onClick={() => { onNavigate('register'); setDropdownOpen(false); }}
                            style={{
                              display: 'block', width: '100%', padding: '11px 18px',
                              background: 'none', border: 'none', textAlign: 'left',
                              color: 'var(--gold, #d4a053)', fontSize: 14, fontWeight: 600,
                              cursor: 'pointer', transition: 'background 0.15s',
                            }}
                            onMouseEnter={(e) => { e.currentTarget.style.background = 'rgba(212,160,83,0.08)'; }}
                            onMouseLeave={(e) => { e.currentTarget.style.background = 'none'; }}
                          >Get Started</button>
                        </>
                      )}
                    </div>
                  )}
                </div>
              ) : (
                /* Desktop: Full welcome text + buttons */
                user ? (
                  <>
                    <span style={{ fontSize: 13, color: 'var(--slate)', marginRight: 10, fontWeight: 500 }}>
                      Welcome, <span style={{ color: '#fff' }}>{user.name}</span>
                    </span>
                    <button
                      className="btn btn--primary btn--sm"
                      onClick={logout}
                    >Logout</button>
                  </>
                ) : (
                  <>
                    <button
                      className="btn btn--ghost btn--sm"
                      onClick={() => onNavigate('login')}
                    >Login</button>
                    <button
                      className="btn btn--primary btn--sm"
                      onClick={() => onNavigate('register')}
                    >Get Started</button>
                  </>
                )
              )}
            </div>
          </div>

          {/* Row 2: Nav links for tablet only (mobile uses hamburger) */}
          {compact && !mobile && (
            <ul className="tic-header__nav tic-header__nav--row2">
              {navLinks.map((item) => {
                const isActive = activeView === item.view;
                return (
                  <li key={item.view}>
                    <button
                      className="nav-link-luxury"
                      onClick={() => onNavigate(item.view)}
                      aria-current={isActive ? 'page' : undefined}
                      style={{
                        background: 'none', border: 'none', cursor: 'pointer',
                        color: isActive ? '#fff' : 'rgba(255,255,255,0.55)',
                        fontSize: 13, fontWeight: isActive ? 600 : 500,
                        fontFamily: 'var(--font-body)', padding: '6px 0',
                        borderBottom: isActive ? '2px solid var(--gold)' : '2px solid transparent',
                        transition: 'color 0.2s, border-color 0.2s',
                        whiteSpace: 'nowrap',
                      }}
                    >
                      {item.label}
                    </button>
                  </li>
                );
              })}
            </ul>
          )}

        </div>
      </div>
    </nav>
  );
};

export default Header;
