import { useState, useEffect } from 'react';

const getBreakpoints = () => ({
  mobile: window.innerWidth <= 768,
  tablet: window.innerWidth > 768 && window.innerWidth <= 1024,
  desktop: window.innerWidth > 1024,
});

export const useResponsive = () => {
  const [bp, setBp] = useState(getBreakpoints);

  useEffect(() => {
    const handler = () => setBp(getBreakpoints());
    window.addEventListener('resize', handler);
    return () => window.removeEventListener('resize', handler);
  }, []);

  return bp;
};
