import React, { useState, useEffect, useRef } from "react";
import PlayerToken from "./PlayerToken";
import { FaDoorOpen, FaStar } from "react-icons/fa";
import { GiCardRandom } from "react-icons/gi";
import styles from "./GameBoard.module.css";
import { socket } from "../../socket";

const colors = [
  "#f94144", "#f3722c", "#f8961e", "#f9844a", "#f9c74f",
  "#90be6d", "#43aa8b", "#577590", "#277da1", "#4cc9f0",
  "#9d4edd", "#c77dff", "#ff6f91", "#ffb6b9", "#ffd166",
  "#06d6a0", "#118ab2", "#ef476f", "#a29bfe", "#fab1a0",
];

const GameBoard = ({
  // === PLAYER 1 ===
  player,
  playerIcon,
  playerPosition,
  playerActiveTile,

  // === PLAYER 2 ===
  player2,
  player2Icon,
  player2Position,
  player2ActiveTile,

  // === GAME STATE ===
  currentPlayer,
  activeTile,
  onCloseTile,
  diceResult,
  session_name,

  // === QUESTION ===
  questionData,
  selectedAnswer,
  onSelectAnswer,
  onCheckAnswer,
  isAnswerChecked,
  isAnswerCorrect,
}) => {
  const [shinyTiles, setShinyTiles] = useState([]);
  const [randomBgNumber, setRandomBgNumber] = useState(1);
  const [bgStyle, setBgStyle] = useState({
    tile_color: "#fff",
    btn_color: "#fff",
  });
  const [boardSettings, setBoardSettings] = useState([]);

  // =====================================================
  // AUDIO (AUTOPLAY SAFE)
  // =====================================================
  const audioRef = useRef({
    swipe: null,
    reveal: null,
    correct: null,
    wrong: null,
    unlocked: false,
  });

  useEffect(() => {
    audioRef.current.swipe = new Audio("/asset/audio/swipe.mp3");
    audioRef.current.reveal = new Audio("/asset/audio/reveal.wav");
    audioRef.current.correct = new Audio("/asset/audio/correct-answer.wav");
    audioRef.current.wrong = new Audio("/asset/audio/wrong-answer.wav");
  }, []);

  const unlockAudio = () => {
    if (audioRef.current.unlocked) return;
    audioRef.current.unlocked = true;

    Object.values(audioRef.current).forEach((a) => {
      if (a instanceof Audio) {
        a.play()
          .then(() => {
            a.pause();
            a.currentTime = 0;
          })
          .catch(() => {});
      }
    });
  };

  const playAudio = (key) => {
    if (!audioRef.current.unlocked) return;
    audioRef.current[key]?.play().catch(() => {});
  };

  // =====================================================
  // EFFECTS
  // =====================================================

  // shiny tiles animation
  useEffect(() => {
    const interval = setInterval(() => {
      const r = [];
      while (r.length < 3) {
        const n = Math.floor(Math.random() * 100) + 1;
        if (!r.includes(n)) r.push(n);
      }
      setShinyTiles(r);
    }, 2000);

    return () => clearInterval(interval);
  }, []);

  // answer feedback sound
  useEffect(() => {
    if (!audioRef.current.unlocked) return;
    if (isAnswerChecked) {
      isAnswerCorrect ? playAudio("correct") : playAudio("wrong");
    }
  }, [isAnswerChecked, isAnswerCorrect]);

  // random popup background
  useEffect(() => {
    if (activeTile !== null) {
      setRandomBgNumber(Math.floor(Math.random() * 34) + 1);
    }
  }, [activeTile]);

  // fetch background color
  useEffect(() => {
    const fetchBgStyle = async () => {
      try {
        const res = await fetch(
          `${import.meta.env.VITE_API_URL}/api/bg-style?bg=Background-${randomBgNumber}.png`
        );
        const data = await res.json();
        setBgStyle({
          tile_color: data?.tile_color || "#fff",
          btn_color: data?.btn_color || "#fff",
        });
      } catch {}
    };
    fetchBgStyle();
  }, [randomBgNumber]);

  // =====================================================
  // SOCKET – BOARD REALTIME
  // =====================================================
  useEffect(() => {
    if (!session_name || !player) return;

    if (!socket.connected) socket.connect();

    socket.emit("join-room", {
      session_name,
      player,
    });

    socket.emit("get-board-setting", { session_name });

    socket.on("board-setting-updated", (data) => {
      setBoardSettings(data);
    });

    socket.on("force-refresh-board", () => {
      socket.emit("get-board-setting", { session_name });
    });

    return () => {
      socket.off("board-setting-updated");
      socket.off("force-refresh-board");
    };
  }, [session_name, player]);

  // =====================================================
  // RENDER BOARD + PLAYER TOKEN
  // =====================================================
  const generateBoard = () => {
    const board = [];
    let isLTR = true;

    for (let row = 0; row < 10; row++) {
      const base = row * 10;
      const rowTiles = [];

      for (let col = 0; col < 10; col++) {
        const index = isLTR ? col : 9 - col;
        const num = base + index + 1;

        const isP1 = playerPosition === num;
        const isP2 = player2Position === num;

        rowTiles.push(
          <div
            key={num}
            className={`${styles.tile}
              ${shinyTiles.includes(num) ? styles.shine : ""}
              ${(isP1 || isP2) ? styles.active : ""}`}
            style={{
              backgroundColor: colors[(num - 1) % colors.length],
              position: "relative", // ⬅️ WAJIB
            }}
            onClick={unlockAudio}
          >
            {/* ================= PLAYER TOKEN ================= */}
            {isP1 && (
              <PlayerToken
                icon={playerIcon || "Default_kid.png"}
                isActive={currentPlayer === player}
              />
            )}

            {isP2 && (
              <PlayerToken
                icon={player2Icon || "Default_parent.png"}
                isActive={currentPlayer === player2}
              />
            )}

            {/* ================= START TILE (1) ================= */}
            {num === 1 && (
              <img
                src="/images/start.png"
                alt="Start"
                style={{
                  position: "absolute",
                  inset: 0,
                  width: "100%",
                  height: "100%",
                  objectFit: "contain",
                  opacity: 0.9,
                  pointerEvents: "none",
                }}
              />
            )}

            {/* ================= TROPHY TILE (100) ================= */}
            {num === 100 && (
              <img
                src="/images/trophy.png"
                alt="Finish"
                style={{
                  position: "absolute",
                  inset: 0,
                  width: "100%",
                  height: "100%",
                  objectFit: "contain",
                  opacity: 0.9,
                  pointerEvents: "none",
                }}
              />
            )}

            {/* ================= TILE NUMBER ================= */}
            {!isP1 && !isP2 && num !== 1 && num !== 100 && (
              <span className={styles.tileNumber}>{num}</span>
            )}

            {/* ================= STAR / CARD ================= */}
            {boardSettings
              .filter(
                (b) =>
                  b.tile === num &&
                  (b.attribute === "Star" || b.attribute === "Card")
              )
              .map((b) =>
                b.attribute === "Star" ? (
                  <FaStar
                    key={b.id}
                    size={20}
                    color="#FFD700"
                    style={{
                      position: "absolute",
                      top: 4,
                      right: 4,
                      zIndex: 5,
                    }}
                  />
                ) : (
                  <GiCardRandom
                    key={b.id}
                    size={20}
                    color="#fff"
                    style={{
                      position: "absolute",
                      top: 4,
                      right: 4,
                      zIndex: 5,
                    }}
                  />
                )
              )}
          </div>
        );
      }

      isLTR = !isLTR;
      board.unshift(...rowTiles);
    }

    return board;
  };

  // =====================================================
  // UI
  // =====================================================
  return (
    <div className={styles.board} onClick={unlockAudio}>
      {generateBoard()}

      {/* SNAKE & LADDER */}
      {boardSettings
        .filter((b) => b.attribute === "Snake" || b.attribute === "Ladder")
        .map((b) => (
          <img
            key={b.id}
            src={`/images/Clip Art/${b.pict}`}
            alt={b.attribute}
            className={
              b.attribute === "Ladder"
                ? styles.ladderImage
                : styles.snakeImage
            }
            style={{
              position: "absolute",
              top: `${b.pos_y}%`,
              left: `${b.pos_x}%`,
              width: `${b.width}%`,
              height: `${b.height}%`,
              transform: `rotate(${b.rotate}deg)`,
            }}
          />
        ))}

      {/* CLOSE TILE BUTTON */}
      {activeTile && (
        <button
          className={styles.tileCloseButton}
          onClick={() => {
            playAudio("reveal");
            onCloseTile();
          }}
          style={{
            borderColor: bgStyle.btn_color,
            color: bgStyle.btn_color,
          }}
        >
          <FaDoorOpen size={26} />
        </button>
      )}
    </div>
  );
};

export default GameBoard;
