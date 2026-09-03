import React, { useState } from 'react';

const DiceRoller = ({ onRoll }) => {
  const [rolling, setRolling] = useState(false);

  const rollDice = () => {
    if (rolling) return;

    setRolling(true);
    const result = Math.floor(Math.random() * 6) + 1;

    setTimeout(() => {
      setRolling(false);
      onRoll(result);
    }, 800);
  };

  return (
    <div
      style={{
        position: 'absolute',
        bottom: '30px',
        left: '50%',
        transform: 'translateX(-50%)',
        zIndex: 10,
      }}
    >
      <button
        onClick={rollDice}
        disabled={rolling}
        style={{
          padding: '14px 28px',
          fontSize: '20px',
          fontFamily: '"Comic Sans MS", "Comic Neue", cursive, sans-serif',
          backgroundColor: '#ffcc00',
          color: '#333',
          border: '3px solid #ff9900',
          borderRadius: '16px',
          cursor: rolling ? 'not-allowed' : 'pointer',
          boxShadow: '0 4px 8px rgba(0, 0, 0, 0.3)',
          transition: 'all 0.3s ease-in-out',
        }}
        onMouseOver={(e) => {
          if (!rolling) e.target.style.backgroundColor = '#ffe066';
        }}
        onMouseOut={(e) => {
          if (!rolling) e.target.style.backgroundColor = '#ffcc00';
        }}
      >
        {rolling ? '🎲 Mengocok...' : '🎲 Lempar Dadu'}
      </button>
    </div>
  );
};

export default DiceRoller;
