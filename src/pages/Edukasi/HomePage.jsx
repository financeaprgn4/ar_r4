import React, { useEffect, useState } from 'react';
import { FaUserCircle , FaHistory, FaTimes } from "react-icons/fa";
import SnakeLadderGame from './SnakeLadderGame';
import styles from './HomePage.module.css';

const HomePage = ({ onStartGame }) => {
  const [showDialog, setShowDialog] = useState(false);
  const [selectedGame, setSelectedGame] = useState(null);
  const [hasHistory, setHasHistory] = useState(false);
  const [selectedMode, setSelectedMode] = useState('single');
  const [showSnakeGame, setShowSnakeGame] = useState(false);
  const [username, setUsername] = useState("Guest");

  useEffect(() => {
    const storedUsername = sessionStorage.getItem("username");

    if (storedUsername && storedUsername.trim() !== "") {
      setUsername(storedUsername);
    } else {
      setUsername("Guest");
    }
  }, []);

  const click = new Audio('/asset/audio/click.wav');
  const swipe = new Audio('/asset/audio/swipe.mp3');

  const handleGameClick = (gameName) => {
    click.play();
    click.onended = () => {
      setSelectedGame(gameName);
      setShowDialog(true);
    };
  };

  const handleModeChange = (mode) => {
    click.currentTime = 0;
    click.play();
    setSelectedMode(mode);
  };

  const handleHoverSound = () => {
    swipe.play();
  };

  const handleCloseDialog = () => {
    click.currentTime = 0;
    click.play();

    click.onended = () => {
      setShowDialog(false);
      setSelectedGame(null);
      click.onended = null;
    };
  };

  useEffect(() => {
    if (selectedGame) {
      const checkHistory = async () => {
        try {
          const response = await fetch(
            `${import.meta.env.VITE_API_URL}/api/game-session/check?game=${encodeURIComponent(selectedGame)}`
          );
          const data = await response.json();
          setHasHistory(data.exists);
        } catch (error) {
          console.error("Gagal memeriksa history game:", error);
          setHasHistory(false);
        }
      };

      checkHistory();
    }
  }, [selectedGame]);

  const handleNewGameClick = async () => {
    const now = new Date();
    const formattedDatetime = now.toISOString().slice(0, 19).replace('T', ' ');
    const sessionName = `${selectedGame} ${formattedDatetime}`;

    const payload = {
      game_name: selectedGame,
      session_name: sessionName,
      last_play: formattedDatetime,
      status: 'NEW',
      mode: selectedMode,
    };

    try {
      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/game-session/new`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const result = await response.json();
      if (result.success) {
        setShowSnakeGame(true);
      } else {
        alert('Gagal menyimpan game: ' + result.message);
      }
    } catch (err) {
      console.error('Error saving game:', err);
    }
  };

  if (showSnakeGame) {
    return (
      <SnakeLadderGame
        onBackToHome={() => {
          setShowSnakeGame(false);
          setShowDialog(false);
        }}
      />
    );
  }

  const handleProfileClick = () => {
    click.currentTime = 0;
    click.play();
    click.onended = () => {
      alert("Menu Profil (belum diimplementasikan)");
      // nanti bisa diganti buka modal / navigate
    };
  };

  return (
    <div className={styles.gameContainer}>
      {/* Dekorasi */}
      <img src="/images/root.png" alt="Root Icon" className={styles.decorRoot} />
      <img src="/images/monkey.png" alt="Monkey Icon" className={`${styles.decorMonkey} ${styles.swingMonkey}`} />
      <img src="/images/Cartoon-1.png" alt="Karakter" className={styles.decorCartoon} />
      <img src="/images/Clip Art/book.png" alt="book" className={styles.decorBook} />
      <img src="/images/Clip Art/awan.png" alt="awan" className={styles.decorAwan} />

      {/* Konten Utama */}
      <div className={styles.homeContent}>
        {!showDialog && (
          <div className={styles.gameOptions}>
            {[
              { id: 1, name: 'Ular Tangga', enabled: true },
              { id: 2, name: 'X O X', enabled: false },
              { id: 3, name: 'Mencocokan', enabled: false }
            ].map((game) => (
              <div
                key={game.id}
                className={`${styles.gameCard} ${!game.enabled ? styles.disabledCard : ''}`}
                onClick={game.enabled ? () => handleGameClick(game.name) : undefined}
                onMouseEnter={handleHoverSound} // tetap ada hover sound meskipun game disable
              >
                <img src="/images/Clip Art/game.png" alt={game.name} />
                <span className={styles.gameName}>{game.name}</span>

                {!game.enabled && (
                  <img
                    src="/images/Clip Art/coming_soon.png"
                    alt="Coming Soon"
                    className={styles.comingSoon}
                  />
                )}
              </div>
            ))}
          </div>
        )}

      </div>

      {/* Dialog Pengaturan */}
      {showDialog && (
        <div className={styles.gameDialogOverlay}>
          <div className={styles.gameDialog}>
            {/* Header */}
            <div className={styles.dialogHeader}>
              <h2 className={styles.gameTitle}>{selectedGame}</h2>
            </div>

            {/* Body - pilihan mode */}
            <div className={styles.dialogBody}>
              <div className={styles.playerOptionsGroup}>
                <label className={styles.playerOption}>
                  <input
                    type="radio"
                    name="mode"
                    value="single"
                    checked={selectedMode === 'single'}
                    onChange={() => handleModeChange('single')}
                  />
                  <span>Single Player</span>
                </label>

                <label className={styles.playerOption}>
                  <input
                    type="radio"
                    name="mode"
                    value="multi"
                    checked={selectedMode === 'multi'}
                    onChange={() => handleModeChange('multi')}
                  />
                  <span>2 Player</span>
                </label>
              </div>

              <button className={styles.newGameButton} onClick={handleNewGameClick}>
                New Game
              </button>
            </div>

            {/* Actions - pojok kanan bawah */}
            <div className={styles.dialogActions}>
              {hasHistory && (
                <button
                  className={styles.circleButton}
                  title="History"
                  onClick={() => {
                    click.play();
                    if (selectedGame === 'Ular Tangga') {
                      setShowSnakeGame(true);
                    }
                  }}
                >
                  <FaHistory className={styles.icon} />
                </button>
              )}

              <button
                className={styles.circleButton}
                onClick={handleCloseDialog}
                title="Tutup"
              >
                <FaTimes className={styles.icon} />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Floating Profile Button with Username */}
      <button
        className={styles.floatingProfileButton}
        onClick={handleProfileClick}
        onMouseEnter={handleHoverSound}
        title="Profil"
      >
        <FaUserCircle size={26} />
        <span className={styles.profileName}>{username}</span>
      </button>
    </div>
  );
};

export default HomePage;
