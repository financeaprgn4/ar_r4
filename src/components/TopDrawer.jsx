import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';

export default function TopDrawer({ isOpen, onClose, children, height = '350px' }) {
  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          className="fixed top-0 left-0 w-full bg-gradient-to-b from-blue-500/80 to-purple-500/80 backdrop-blur-sm border-b z-[9999] p-4 overflow-visible"
          style={{
            maxHeight: height,
            height: 'auto',
          }}
          initial={{ y: "-100%" }}
          animate={{ y: 0 }}
          exit={{ y: "-100%" }}
          transition={{ duration: 0.3 }}
        >
          {children}
        </motion.div>
      )}
    </AnimatePresence>
  );
}
