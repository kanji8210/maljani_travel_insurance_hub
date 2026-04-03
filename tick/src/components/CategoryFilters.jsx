import React, { useMemo } from 'react';
import { useQuery } from 'urql';

const GET_POLICY_REGIONS = `
  query GetPolicyRegions {
    policies(first: 100) {
      nodes {
        regions { nodes { slug name } }
      }
    }
  }
`;

const CategoryFilters = ({ onSelect }) => {
  const [active, setActive] = React.useState(null);
  const [{ data }] = useQuery({ query: GET_POLICY_REGIONS });

  /* Derive unique regions that have at least one policy. */
  const activeRegions = useMemo(() => {
    const nodes = data?.policies?.nodes || [];
    const map = new Map(); // slug → name
    nodes.forEach(p => {
      (p.regions?.nodes || []).forEach(r => {
        if (r.slug && r.name && !map.has(r.slug)) map.set(r.slug, r.name);
      });
    });
    return Array.from(map.entries()).map(([slug, name]) => ({ slug, name }));
  }, [data]);

  if (activeRegions.length === 0) return null;

  const handleClick = (slug) => {
    setActive(slug);
    onSelect(slug);
  };

  const btnStyle = (slug) => ({
    display: 'flex', alignItems: 'center', gap: 8, padding: '10px 20px', borderRadius: 100,
    background: active === slug ? 'rgba(49,99,49,0.18)' : 'var(--glass-bg)',
    border: `1px solid ${active === slug ? 'rgba(49,99,49,0.45)' : 'var(--glass-border)'}`,
    color: active === slug ? '#86efac' : 'rgba(255,255,255,0.65)',
    fontFamily: 'var(--font-body)', fontSize: 13, fontWeight: 600,
    cursor: 'pointer', transition: 'all 0.22s', whiteSpace: 'nowrap',
  });

  return (
    <div style={{ position: 'relative', zIndex: 1, padding: '0 0 40px' }}>
      <div className="container">
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
          <p className="section-label" style={{ margin: 0 }}>✈️ Popular Destinations</p>
          <a href="#policy-showcase" style={{ fontSize: 13, color: 'var(--slate)', textDecoration: 'none' }}>View all policies →</a>
        </div>
        <div style={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch', scrollbarWidth: 'none' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, width: 'max-content', padding: '4px 2px' }}>
            {/* "All" pill to clear the region filter */}
            <button onClick={() => handleClick(null)} style={btnStyle(null)}>
              🌐 All Destinations
            </button>
            {activeRegions.map(r => (
              <button key={r.slug} onClick={() => handleClick(r.slug)} style={btnStyle(r.slug)}>
                {r.name}
              </button>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default CategoryFilters;
