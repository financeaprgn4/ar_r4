const { Server } = require("socket.io");

const io = new Server(3001, {
  cors: {
    origin: "*",
  },
});

console.log("🚀 Socket server running on port 3001");

// ===== GAME STATE (IN MEMORY) =====
const gameRooms = {};

io.on("connection", (socket) => {
  console.log("🔌 Client connected:", socket.id);

  // ===============================
  // JOIN ROOM
  // ===============================
  socket.on("join-room", ({ session_name, player }) => {
    if (!session_name || !player) return;

    socket.join(session_name);

    gameRooms[session_name] ??= { players: {} };
    gameRooms[session_name].players[player] ??= { position: 1 };

    console.log(`👥 ${player} joined room ${session_name}`);
  });

  // ===============================
  // INITIAL LOAD BOARD FROM LARAVEL
  // ===============================
  socket.on("get-board-setting", async ({ session_name }) => {
    try {
      const response = await fetch(
        "http://localhost:8000/api/game-board-setting"
      );
      const data = await response.json();

      io.to(session_name).emit("board-setting-updated", data);
      console.log("📦 Board setting sent to room", session_name);
    } catch (error) {
      console.error("❌ Failed to fetch board setting:", error);
    }
  });

  // ===============================
  // PLAYER MOVE
  // ===============================
  socket.on("move-player", ({ session_name, player, steps }) => {
    if (!gameRooms[session_name]?.players[player]) return;

    gameRooms[session_name].players[player].position += steps;

    io.to(session_name).emit("player-moved", {
      player,
      position: gameRooms[session_name].players[player].position,
    });

    console.log(
      `🎲 ${player} moved to ${gameRooms[session_name].players[player].position}`
    );
  });

  // ===============================
  // ADMIN UPDATE BOARD (REALTIME)
  // ===============================
  socket.on("admin-board-updated", ({ session_name, boardSettings }) => {
    io.to(session_name).emit("board-setting-updated", boardSettings);
    console.log("♻ Board updated realtime for room", session_name);
  });

  socket.on("admin-board-updated", ({ session_name }) => {
    io.to(session_name).emit("force-refresh-board");
  });

  // ===============================
  // DISCONNECT
  // ===============================
  socket.on("disconnect", () => {
    console.log("❌ Client disconnected:", socket.id);
  });
});
