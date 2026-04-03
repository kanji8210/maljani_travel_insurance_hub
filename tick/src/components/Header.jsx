import React, { useEffect, useState } from 'react';
import { useAuth } from '../lib/AuthContext';

const Header = ({ onNavigate, activeView, canGoBack, canGoForward, onBack, onForward }) => {
  const { user, role, logout, loading } = useAuth();
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const handler = () => setScrolled(window.scrollY > 40);
    window.addEventListener('scroll', handler, { passive: true });
    return () => window.removeEventListener('scroll', handler);
  }, []);

  const navLinks = React.useMemo(() => {
    const links = [
      { label: 'Travel Policies', view: 'catalog' },
    ];

    if (user) {
      links.push({ 
        label: role === 'insured' ? 'My Policies' : 'Dashboard', 
        view: 'dashboard' 
      });
    }

    links.push({ label: 'Verify Policy', view: 'verify' });
    links.push({ label: 'About', view: 'about' });

    if (!user) {
      links.splice(1, 0, { label: 'For Agencies', view: 'agencies' });
    }

    return links;
  }, [role, user]);

  return (
    <nav
      style={{
        position: 'fixed', top: 0, left: 0, right: 0, zIndex: 100,
        padding: '20px 0',
        transition: 'background 0.35s, backdrop-filter 0.35s, border-color 0.35s',
        background: scrolled ? 'rgba(8,14,39,0.88)' : 'transparent',
        backdropFilter: scrolled ? 'blur(24px)' : 'none',
        borderBottom: scrolled ? '1px solid rgba(255,255,255,0.10)' : '1px solid transparent',
      }}
      role="navigation" aria-label="Main navigation"
    >
      <div className="container">
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>

          {/* Back / Forward + Logo */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <button
              onClick={onBack}
              disabled={!canGoBack}
              aria-label="Go back"
              style={{
                background: 'none', border: '1px solid',
                borderColor: canGoBack ? 'rgba(255,255,255,0.18)' : 'rgba(255,255,255,0.07)',
                borderRadius: 8, width: 32, height: 32,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                cursor: canGoBack ? 'pointer' : 'default',
                color: canGoBack ? 'rgba(255,255,255,0.65)' : 'rgba(255,255,255,0.2)',
                fontSize: 16, lineHeight: 1, transition: 'all 0.2s', flexShrink: 0,
              }}
            >←</button>
            <button
              onClick={onForward}
              disabled={!canGoForward}
              aria-label="Go forward"
              style={{
                background: 'none', border: '1px solid',
                borderColor: canGoForward ? 'rgba(255,255,255,0.18)' : 'rgba(255,255,255,0.07)',
                borderRadius: 8, width: 32, height: 32,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                cursor: canGoForward ? 'pointer' : 'default',
                color: canGoForward ? 'rgba(255,255,255,0.65)' : 'rgba(255,255,255,0.2)',
                fontSize: 16, lineHeight: 1, transition: 'all 0.2s', flexShrink: 0,
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
                style={{ width: 38, height: 38, objectFit: 'contain', borderRadius: 10, flexShrink: 0 }}
              />
              <span style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 18, color: '#fff', lineHeight: 1.1 }}>
                TIC<span style={{ color: 'var(--gold)' }}>-Kenya</span>
              </span>
            </button>
          </div>

          {/* Nav links */}
          <ul style={{ display: 'flex', alignItems: 'center', gap: 36, listStyle: 'none', margin: 0, padding: 0 }}>
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

          {/* Actions */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            {loading ? (
              <div style={{ width: 100, height: 32, background: 'rgba(255,255,255,0.05)', borderRadius: 8, animate: 'pulse 1.5s infinite' }} />
            ) : user ? (
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
            )}
          </div>

        </div>
      </div>
    </nav>
  );
};

export default Header;
