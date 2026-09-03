import React, { useEffect, useState } from 'react';
import { FaTimes } from "react-icons/fa";
import { motion, AnimatePresence } from 'framer-motion';

export default function BottomDrawer({ isOpen, onClose, children, height = '300px' }) {
  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          className="fixed bottom-0 left-0 w-full bg-gradient-to-br from-blue-500/80 to-purple-500/80 backdrop-blur-sm border-t z-50 p-4"
          style={{ height }}
          initial={{ y: "100%" }}
          animate={{ y: 0 }}
          exit={{ y: "100%" }}
          transition={{ duration: 0.3 }}
        >
          <button onClick={onClose} className="absolute top-2 right-2 bg-white rounded-full p-1 text-red-500 shadow">
            <FaTimes className="w-4 h-4" />
          </button>
          {children}
        </motion.div>
      )}
    </AnimatePresence>
  );
}
