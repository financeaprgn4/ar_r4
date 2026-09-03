console.log("=== NODE ENV DEBUG ===");

console.log("Node version:", process.version);
console.log("Exec path:", process.execPath);
console.log("Working dir:", process.cwd());

console.log("\n--- ENV ---");
console.log("USER:", process.env.USERNAME);
console.log("TEMP:", process.env.TEMP);
console.log("TMP:", process.env.TMP);
console.log("PATH:", process.env.PATH);

console.log("\n--- DNS TEST ---");
require('dns').lookup('ibank.bri.co.id', (err, address) => {
  console.log("DNS:", err || address);
});

console.log("\n--- HTTPS TEST ---");

const req = require('https').get('https://ibank.bri.co.id', res => {
  console.log("HTTPS OK:", res.statusCode);
  res.resume();
  process.exit(0); // ✅ penting
});

req.on('error', err => {
  console.error("HTTPS ERROR:", err.message);
  process.exit(1); // ✅ penting
});

// ⛑️ fallback kalau hang
setTimeout(() => {
  console.log("Force exit (timeout)");
  process.exit(0);
}, 5000);
