import React from "react";

const PlayerToken = ({ icon = "default.png" }) => {
  const imageSrc = `/images/karakter/${icon}`;

  return (
    <img
      src={imageSrc}
      alt="Player Icon"
      onError={(e) => {
        console.error("❌ Icon not found:", imageSrc);
        e.target.src = "/images/karakter/default.png";
      }}
      style={{
        width: "70px",
        height: "70px",
        objectFit: "contain",
        borderRadius: "50%",
        border: "2px solid #fff",
        boxShadow: "0 0 4px rgba(0,0,0,0.4)",
        position: "absolute",
        top: "50%",
        left: "50%",
        transform: "translate(-50%, -50%)",
        zIndex: 10,
        pointerEvents: "none",
      }}
    />
  );
};

export default PlayerToken;
