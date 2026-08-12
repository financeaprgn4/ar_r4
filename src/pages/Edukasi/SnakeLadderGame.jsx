import React, { useState, useEffect } from 'react';
import GameBoard from './GameBoard';
import DiceRoller from './DiceRoller';
import Dice3D from './Dice3D';
import { FiSettings } from 'react-icons/fi';
import { FaSave, FaPencilAlt, FaTimes, FaDoorClosed } from 'react-icons/fa';
import styles from './SnakeLadderGame.module.css';

const SnakeLadderGame = ({ onBackToHome }) => {
  const [positionMap, setPositionMap] = useState({});
  const [currentPlayer, setCurrentPlayer] = useState(null);
  const [sessionName, setSessionname] = useState(0);
  const [showDice, setShowDice] = useState(false);
  const [isRolling, setIsRolling] = useState(false);
  const [currentDice, setCurrentDice] = useState(null);
  const [isWaitingMove, setIsWaitingMove] = useState(false);
  const [movementPath, setMovementPath] = useState([]);
  const [activeTile, setActiveTile] = useState(null);
  const [sessionHistory, setSessionHistory] = useState([]);
  const [loadedSession, setLoadedSession] = useState(null);
  const [showSettings, setShowSettings] = useState(false);  
  const [isSessionLoaded, setIsSessionLoaded] = useState(false);
  const [questionData, setQuestionData] = useState(null);
  const [selectedAnswer, setSelectedAnswer] = useState(null);
  const [isAnswerChecked, setIsAnswerChecked] = useState(false);
  const [isAnswerCorrect, setIsAnswerCorrect] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [characters, setCharacters] = useState([]);

  const click = new Audio('/asset/audio/click.wav');
  const selectAnswer = new Audio('/asset/audio/select-answer.wav');
  const reveal = new Audio('/asset/audio/reveal.wav');

  const handleExitClick = () => {
    click.play();
    click.onended = () => {
      onBackToHome();
    };
  };

  const handleChangeIcon = () => {
    setIsModalOpen(true);
  };

  useEffect(() => {
    if (isModalOpen) {
      fetch(`${import.meta.env.VITE_API_URL}/api/characters`)
        .then((res) => res.json())
        .then((data) => setCharacters(data))
        .catch((err) => console.error(err));
    }
  }, [isModalOpen]);

  useEffect(() => {
    const fetchHistory = async () => {
      try {
        const response = await fetch(`${import.meta.env.VITE_API_URL}/api/game-session`);
        const data = await response.json();
        setSessionHistory(data);
      } catch (error) {
        console.error("Gagal memuat data session:", error);
      }
    };

    fetchHistory();
  }, []);

  const handleLoadSession = (sessionName) => {
    click.play();
    click.onended = () => {
      const selected = sessionHistory.find(
        (s) => s.session_name === sessionName
      );

      if (selected) {
        setLoadedSession({
          session_name: selected.session_name,
          mode: selected.mode || '',

          player: selected.player || 'Player 1',
          playerTurn: selected.turn || '',
          playerIcon: selected.playerIcon || null,
          position: selected.position || 0,
          total_question: selected.total_question || 0,
          benar: selected.benar || 0,
          salah: selected.salah || 0,

          player2: selected.player2 || 'Player 2',
          player2Turn: selected.player2Turn || '',
          player2Icon: selected.player2Icon || null,
          position2: selected.position2 || 0,
          total_question2: selected.total_question2 || 0,
          benar2: selected.benar2 || 0,
          salah2: selected.salah2 || 0,
        });

        setPositionMap({
          [selected.player]: selected.position || 0,
          [selected.player2]: selected.position2 || 0,
        });

        const turnPlayer =
          selected.mode === 'Multi Player'
            ? (selected.playerTurn === 'On' ? selected.player : selected.player2)
            : selected.player;

        setCurrentPlayer(turnPlayer);
        setSessionname(selected.session_name || '');
        setIsSessionLoaded(true);
      }
    };
  };

  const handleRoll = (dice) => {
    setCurrentDice(null);
    setIsRolling(true);
    setShowDice(true);

    const maxPos = 100;
    const current = positionMap[currentPlayer] || 0;
    const steps = [];

    const forwardSteps = Math.min(dice, maxPos - current);
    let temp = current;

    for (let i = 1; i <= forwardSteps; i++) {
      temp++;
      steps.push(temp);
    }

    const remainingSteps = dice - forwardSteps;
    for (let i = 1; i <= remainingSteps; i++) {
      temp--;
      steps.push(temp);
    }

    setTimeout(() => {
      setCurrentDice(dice);
      setIsRolling(false);
      setMovementPath(steps);
    }, 1500);
  };

  const handleHideDice = () => {
    setShowDice(false);
    setIsWaitingMove(true);
  };

  const fetchSessionDetail = async () => {
    if (!sessionName || !loadedSession) return;

    try {
      const fetchPlayerData = async (playerKey) => {
        const res = await fetch(
          `${import.meta.env.VITE_API_URL}/api/game-session-detail?session_name=${sessionName}&player=${playerKey}`
        );
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || "Gagal fetch detail player");
        return data;
      };

      // Fetch player 1
      const player1Data = await fetchPlayerData(loadedSession.player);

      // Untuk multi player, fetch player 2 juga
      let player2Data = null;
      if (loadedSession.mode === 'Multi Player' && loadedSession.player2) {
        player2Data = await fetchPlayerData(loadedSession.player2);
      }

      // Update state
      setLoadedSession((prev) => ({
        ...prev,
        position: player1Data.position,
        total_question: player1Data.question,
        benar: player1Data.benar,
        salah: player1Data.salah,
        ...(player2Data && {
          position2: player2Data.position,
          total_question2: player2Data.question,
          benar2: player2Data.benar,
          salah2: player2Data.salah,
        }),
      }));
    } catch (err) {
      console.error("Gagal update leaderboard:", err);
    }
  };

  useEffect(() => {
    if (isWaitingMove && movementPath.length > 0 && currentPlayer) {
      const timer = setTimeout(() => {
        const nextPos = movementPath[0];

        setPositionMap((prev) => ({
          ...prev,
          [currentPlayer]: nextPos,
        }));

        setMovementPath((prev) => prev.slice(1));

        if (movementPath.length === 1) {
          setTimeout(() => {
            setActiveTile((prev) => ({
              ...prev,
              [currentPlayer]: nextPos,
            }));

            fetchSessionDetail(); // Refresh data
            setIsAnswerChecked(false);
            setSelectedAnswer(null);
            setIsAnswerCorrect(null);
          }, 1000);
        }
      }, 250);

      return () => clearTimeout(timer);
    } else if (isWaitingMove && movementPath.length === 0) {
      setIsWaitingMove(false);
    }
  }, [movementPath, isWaitingMove, currentPlayer]);

  const isButtonDisabled = showDice || isRolling || isWaitingMove || activeTile !== null;

  const handleEdit = (sessionName) => {
    alert(`Edit session: ${sessionName}`);
  };

  const handleDelete = async (sessionName) => {
    const confirmDelete = window.confirm(`Yakin hapus sesi: ${sessionName}?`);
    if (!confirmDelete) return;

    try {
      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/game-session/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_name: sessionName }),
      });

      const result = await response.json();
      if (result.success) {
        alert("Session berhasil dihapus");
        setSessionHistory((prev) => prev.filter((s) => s.session_name !== sessionName));
      } else {
        alert("Gagal menghapus sesi");
      }
    } catch (error) {
      console.error("Gagal hapus:", error);
      alert("Terjadi kesalahan");
    }
  };

  const [isClosing, setIsClosing] = useState(false);

  const handleOpenSetting = () => {
    click.play();
    click.onended = () => {
      setShowSettings(true);
    };
  };

  const handleCloseSetting = () => {
    click.play();
    setIsClosing(true);
    setTimeout(() => {
      setShowSettings(false);
      setIsClosing(false);
    }, 300);
  };

  useEffect(() => {
    if (!sessionName || !currentPlayer || !activeTile) return;

    // Multi Player
    if (typeof activeTile === 'object') {
      Object.entries(activeTile).forEach(([playerKey, tileValue]) => {
        if (!tileValue) return;

        fetch(`${import.meta.env.VITE_API_URL}/api/game-question?session_game=${sessionName}&player=${currentPlayer}&playerPosition=${tileValue}`)
          .then((res) => {
            if (!res.ok) {
              return res.json().then(err => {
                throw new Error(err.error || 'Gagal mengambil data soal');
              });
            }
            return res.json();
          })
          .then((data) => {
            setQuestionData(prev => ({
              ...prev,
              [playerKey]: data,
            }));
          })
          .catch((err) => console.error('Terjadi kesalahan:', err.message));
      });
    } else {
      // Single Player
      fetch(`${import.meta.env.VITE_API_URL}/api/game-question?session_game=${sessionName}&player=${currentPlayer}&playerPosition=${activeTile}`)
        .then((res) => {
          if (!res.ok) {
            return res.json().then(err => {
              throw new Error(err.error || 'Gagal mengambil data soal');
            });
          }
          return res.json();
        })
        .then((data) => setQuestionData(data))
        .catch((err) => console.error('Terjadi kesalahan:', err.message));
    }
  }, [sessionName, currentPlayer, activeTile]);

  const handleOptionChange = (e) => {
    selectAnswer.play();
    setSelectedAnswer(e.target.value);
  };

  const updateCurrentPlayer = async () => {
    try {
      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/game-session`);
      const sessions = await response.json();

      const currentSession = sessions.find(s => s.session_name === sessionName);
      if (!currentSession) return;

      let nextPlayer;

      if (currentSession.mode === 'Multi Player') {
        if (currentSession.playerTurn === 'On') {
          nextPlayer = currentSession.player;
        } else if (currentSession.player2Turn === 'On') {
          nextPlayer = currentSession.player2;
        } else {
          console.warn("Tidak ditemukan player yang turn-nya 'On'");
          return;
        }
      } else {
        nextPlayer = currentSession.player;
      }

      setCurrentPlayer(nextPlayer);
    } catch (error) {
      console.error("Gagal update currentPlayer:", error);
    }
  };

  const handleCheckAnswer = async () => {
    const currentQuestion = questionData?.[currentPlayer];

    if (!selectedAnswer || !currentQuestion?.question?.code) return;

    const payload = {
      mode: loadedSession.mode,
      currentDice,
      question_code: currentQuestion.question.code,
      selected_answer: selectedAnswer,
      player: currentPlayer,
      session_name: sessionName,
      playerPosition: activeTile?.[currentPlayer],
    };

    console.log("payload : ", payload);
    try {
      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/check-answer`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const data = await response.json();

      if (response.ok) {
        setIsAnswerCorrect(data.correct);
      } else {
        console.error('Gagal mengecek jawaban:', data.error || data.message);
      }

      setIsAnswerChecked(true);
      fetchSessionDetail();
    } catch (error) {
      console.error('Terjadi kesalahan saat memeriksa jawaban:', error);
    }
  };
  
  const handleCloseTile = () => {
    reveal.play();
    setActiveTile(null);
    updateCurrentPlayer();
  };

  return (
    <div className={styles.gameContainer}>
      <img src="/images/root.png" alt="Root Icon" className={styles.decorRoot} />
      <img src="/images/monkey.png" alt="Monkey Icon" className={`${styles.decorMonkey} ${styles.swingMonkey}`} />
      <img src="/images/Cartoon.png" alt="Karakter" className={styles.decorCartoon} />

      <div className={styles.boardWrapper}>
        {(isSessionLoaded && loadedSession?.session_name) && (
          <>
            <GameBoard
              player={loadedSession?.player}
              playerIcon={loadedSession?.playerIcon}
              playerPosition={positionMap[loadedSession?.player]}
              playerActiveTile={activeTile?.[loadedSession?.player] || null}

              player2={loadedSession?.player2}
              player2Icon={loadedSession?.player2Icon}
              player2Position={positionMap[loadedSession?.player2]}
              player2ActiveTile={activeTile?.[loadedSession?.player2] || null}
              
              currentPlayer={currentPlayer}
              onCloseTile={handleCloseTile}
              activeTile={activeTile}
              diceResult={currentDice}
              session_name={sessionName}
              questionData={questionData?.[currentPlayer] || null}
              selectedAnswer={selectedAnswer}
              onSelectAnswer={handleOptionChange}
              onCheckAnswer={handleCheckAnswer}
              isAnswerChecked={isAnswerChecked}
              isAnswerCorrect={isAnswerCorrect}
            />
            {!isButtonDisabled && <DiceRoller onRoll={handleRoll} />}
          </>
        )}
      </div>

      <div className={styles.leaderboard}>
        {!isSessionLoaded &&
          <div className={styles.leaderboardHeader}>
            <h2>📜 History Game</h2>
          </div>
        }

        {!isSessionLoaded ? (
          <ul className={styles.sessionList}>
            {sessionHistory.map((session, index) => (
              <li key={index} className={styles.sessionItem}>
                <span
                  className={styles.sessionLink}
                  onClick={() => handleLoadSession(session.session_name)}
                >
                  {index + 1}. {session.session_name}
                </span>
                <div className={styles.sessionActions}>
                  <button
                    className={styles.editButton}
                    onClick={() => handleEdit(session.session_name)}
                  >
                    <FaPencilAlt className={styles.icon} />
                  </button>
                  <button
                    className={styles.deleteButton}
                    onClick={() => handleDelete(session.session_name)}
                  >
                    <FaTimes className={styles.icon} />
                  </button>
                </div>
              </li>
            ))}
          </ul>
        ) : (
          <div className={styles.selectedSessionInfo}>
            <div className={styles.sessionCard}>
              <div className={styles.sessionRow}>
                <span className={styles.label}>Session:</span>
                <span className={styles.value}>{loadedSession.session_name}</span>
              </div>
              <div className={styles.sessionRow}>
                <span className={styles.label}>Mode:</span>
                <span className={styles.value}>{loadedSession.mode}</span>
              </div>
            </div>

            <div className={styles.sessionCard}>
              <div className={styles.characterBox}>
                <img
                  src={`/images/karakter/${loadedSession?.playerIcon || 'default.png'}`}
                  alt="Karakter Player"
                  className={`${styles.characterImage} ${
                    loadedSession?.player === currentPlayer ? styles.activeBox : ''
                  }`}
                  onClick={handleChangeIcon}
                  style={{ cursor: "pointer" }}
                />
              </div>
              <div className={styles.sessionRow}>
                <span className={styles.label}>Player:</span>
                <span className={styles.value}>{loadedSession.player}</span>
              </div>
              <div className={styles.sessionRow}>
                <span className={styles.label}>Posisi:</span>
                <span className={styles.value}>{loadedSession.position}</span>
              </div>
              <div className={styles.sessionRow}>
                <span className={styles.label}>Total Soal:</span>
                <span className={styles.value}>{loadedSession.total_question}</span>
              </div>
              <div className={styles.sessionRowAnswer}>
                <div className={styles.answerBox}>
                  <span className={styles.label}>Benar:</span>
                  <span className={styles.value}>{loadedSession.benar}</span>
                </div>
                <div className={styles.answerBox}>
                  <span className={styles.label}>Salah:</span>
                  <span className={styles.value}>{loadedSession.salah}</span>
                </div>
              </div>

              {isModalOpen && (
                <div className={styles.modalOverlay}>
                  <div className={styles.modalContent}>
                    <h3>Pilih Icon</h3>
                      <div className={styles.imageGrid}>
                        {characters.map((char) => (
                          <img
                            key={char.id}
                            src={`/images/karakter/${char.pict}`}
                            alt={char.caracter}
                            className={styles.optionImage}
                            onClick={() => handleSelectImage(char.pict)}
                          />
                        ))}
                      </div>
                    <div className={styles.buttonContainer}>
                      <button
                        className={styles.closeButton}
                        onClick={() => setIsModalOpen(false)}
                      >
                        <FaSave style={{ marginRight: "8px" }} />
                        Simpan
                      </button>
                    </div>
                  </div>
                </div>
              )}
            </div>

            {loadedSession.mode === 'Multi Player' && loadedSession.player2 && (
              <div className={styles.sessionCard}>
                <div className={styles.characterBox}>
                  <img
                    src={`/images/karakter/${loadedSession?.player2Icon || 'default.png'}`}
                    alt="Karakter Player"
                    className={`${styles.characterImage} ${
                      loadedSession?.player2 === currentPlayer ? styles.activeBox : ''
                    }`}

                  />
                </div>
                <div className={styles.sessionRow}>
                  <span className={styles.label}>Player:</span>
                  <span className={styles.value}>{loadedSession.player2}</span>
                </div>
                <div className={styles.sessionRow}>
                  <span className={styles.label}>Posisi:</span>
                  <span className={styles.value}>{loadedSession.position2}</span>
                </div>
                <div className={styles.sessionRow}>
                  <span className={styles.label}>Total Soal:</span>
                  <span className={styles.value}>{loadedSession.total_question2}</span>
                </div>
                <div className={styles.sessionRowAnswer}>
                <div className={styles.answerBox}>
                  <span className={styles.label}>Benar:</span>
                  <span className={styles.value}>{loadedSession.benar2}</span>
                </div>
                <div className={styles.answerBox}>
                  <span className={styles.label}>Salah:</span>
                  <span className={styles.value}>{loadedSession.salah2}</span>
                </div>
              </div>
              </div>
            )}
          </div>
        )}
      </div>

      {showDice && (
        <Dice3D
          isRolling={isRolling}
          result={currentDice}
          size={2}
          onHide={handleHideDice}
        />
      )}

      <button
        className={`${styles.floatingExitButton} ${!isSessionLoaded ? styles.shiftedDown : ''}`}
        onClick={handleExitClick}
      >
        <FaDoorClosed size={26} />
      </button>
      
      {isSessionLoaded && (
        <button
          className={styles.floatingMenuButton}
          onClick={handleOpenSetting}
        >
          <FiSettings size={26} />
        </button>
      )}

      {showSettings && (
        <div className={styles.settingsOverlay}>
          <div
            className={`${styles.settingsBox} ${isClosing ? styles.settingsBoxClosing : ''}`}
          >
            <div className={styles.settingsHeader}>
              <h3>⚙️ Pengaturan Game</h3>
              <button
                className={styles.settingsCloseButton}
                onClick={handleCloseSetting}
              >
                ✖
              </button>
            </div>

            <div className={styles.settingsContent}>
              <div className={styles.settingRow}>
                <label>Audio:</label>
                <select>
                  <option>Aktif</option>
                  <option>Nonaktif</option>
                </select>
              </div>
              <div className={styles.settingRow}>
                <label>Tema Board:</label>
                <select>
                  <option>Default</option>
                  <option>Gelap</option>
                  <option>Jungle</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default SnakeLadderGame;
