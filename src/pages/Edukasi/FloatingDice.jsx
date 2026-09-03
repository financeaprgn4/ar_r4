import React, { useEffect, useState } from 'react';
import './FloatingDice.css';

const diceFaces = ['⚀', '⚁', '⚂', '⚃', '⚄', '⚅'];

const FloatingDice = ({ isRolling, result }) => {
  const [displayFace, setDisplayFace] = useState(0);

  useEffect(() => {
    if (isRolling) {
      let i = 0;
      const interval = setInterval(() => {
        setDisplayFace((prev) => (prev + 1) % 6);
        i++;
        if (i > 15) {
          clearInterval(interval);
        }
      }, 100);
      return () => clearInterval(interval);
    } else if (result) {
      setDisplayFace(result - 1);
    }
  }, [isRolling, result]);

  return (
    <div className="floating-dice">
      <span className="dice-face">{diceFaces[displayFace]}</span>
    </div>
  );
};

export default FloatingDice;
