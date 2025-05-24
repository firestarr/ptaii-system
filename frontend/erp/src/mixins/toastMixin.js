// src/mixins/toastMixin.js
export default {
  data() {
    return {
      toasts: [],
      toastIdCounter: 0
    };
  },
  methods: {
    showToast(message, type = 'info', duration = 5000) {
      const toast = {
        id: this.toastIdCounter++,
        message,
        type,
        duration
      };
      
      this.toasts.push(toast);
      
      // Auto remove after duration
      setTimeout(() => {
        this.removeToast(toast.id);
      }, duration);
    },

    removeToast(id) {
      this.toasts = this.toasts.filter(toast => toast.id !== id);
    },

    getToastIcon(type) {
      switch (type) {
        case 'success':
          return 'fas fa-check-circle';
        case 'error':
          return 'fas fa-exclamation-circle';
        case 'warning':
          return 'fas fa-exclamation-triangle';
        default:
          return 'fas fa-info-circle';
      }
    },

    // Helper methods
    $toast: {
      success: (msg) => this.showToast(msg, 'success'),
      error: (msg) => this.showToast(msg, 'error'),
      warning: (msg) => this.showToast(msg, 'warning'),
      info: (msg) => this.showToast(msg, 'info')
    }
  }
};