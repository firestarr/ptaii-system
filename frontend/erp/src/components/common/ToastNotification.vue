<!-- src/components/common/ToastNotification.vue -->
<template>
  <teleport to="body">
    <div class="toast-container">
      <transition-group name="toast" tag="div" class="toast-wrapper">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="toast"
          :class="[`toast-${toast.type}`, { 'toast-dismissing': toast.dismissing }]"
        >
          <div class="toast-icon">
            <i :class="getIcon(toast.type)"></i>
          </div>
          <div class="toast-content">
            <div class="toast-title" v-if="toast.title">{{ toast.title }}</div>
            <div class="toast-message">{{ toast.message }}</div>
          </div>
          <button @click="dismissToast(toast.id)" class="toast-close">
            <i class="fas fa-times"></i>
          </button>
          <div 
            class="toast-progress" 
            :style="{ 
              animationDuration: `${toast.duration}ms`,
              animationPlayState: toast.paused ? 'paused' : 'running'
            }"
          ></div>
        </div>
      </transition-group>
    </div>
  </teleport>
</template>

<script>
export default {
  name: 'ToastNotification',
  data() {
    return {
      toasts: []
    }
  },
  mounted() {
    // Listen for toast events
    this.$root.$on('showToast', this.showToast)
  },
  beforeUnmount() {
    this.$root.$off('showToast', this.showToast)
  },
  methods: {
    showToast({ type = 'info', title = '', message, duration = 5000 }) {
      const id = Date.now() + Math.random()
      const toast = {
        id,
        type,
        title,
        message,
        duration,
        dismissing: false,
        paused: false
      }
      
      this.toasts.push(toast)
      
      // Auto dismiss after duration
      setTimeout(() => {
        this.dismissToast(id)
      }, duration)
    },
    
    dismissToast(id) {
      const index = this.toasts.findIndex(toast => toast.id === id)
      if (index > -1) {
        this.toasts[index].dismissing = true
        setTimeout(() => {
          this.toasts.splice(index, 1)
        }, 300)
      }
    },
    
    getIcon(type) {
      const icons = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle'
      }
      return icons[type] || icons.info
    }
  }
}
</script>

<style scoped>
.toast-container {
  position: fixed;
  top: 2rem;
  right: 2rem;
  z-index: 10000;
  pointer-events: none;
}

.toast-wrapper {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 400px;
}

.toast {
  background: white;
  border-radius: 12px;
  padding: 1rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  border: 1px solid var(--border-color);
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  position: relative;
  overflow: hidden;
  pointer-events: auto;
  transition: all 0.3s ease;
  border-left: 4px solid;
}

.toast-success {
  border-left-color: #10b981;
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, white 100%);
}

.toast-error {
  border-left-color: #ef4444;
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, white 100%);
}

.toast-warning {
  border-left-color: #f59e0b;
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, white 100%);
}

.toast-info {
  border-left-color: #6366f1;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, white 100%);
}

.toast-dismissing {
  transform: translateX(100%);
  opacity: 0;
}

.toast-icon {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 1.1rem;
}

.toast-success .toast-icon {
  color: #10b981;
}

.toast-error .toast-icon {
  color: #ef4444;
}

.toast-warning .toast-icon {
  color: #f59e0b;
}

.toast-info .toast-icon {
  color: #6366f1;
}

.toast-content {
  flex: 1;
  min-width: 0;
}

.toast-title {
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
  font-size: 0.9rem;
}

.toast-message {
  color: var(--text-secondary);
  font-size: 0.85rem;
  line-height: 1.4;
  word-wrap: break-word;
}

.toast-close {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  padding: 0.25rem;
  border-radius: 4px;
  transition: all 0.2s;
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.toast-close:hover {
  background: rgba(0, 0, 0, 0.05);
  color: var(--text-primary);
}

.toast-progress {
  position: absolute;
  bottom: 0;
  left: 0;
  height: 3px;
  background: currentColor;
  width: 100%;
  transform-origin: left;
  animation: toast-progress linear forwards;
}

.toast-success .toast-progress {
  background: #10b981;
}

.toast-error .toast-progress {
  background: #ef4444;
}

.toast-warning .toast-progress {
  background: #f59e0b;
}

.toast-info .toast-progress {
  background: #6366f1;
}

@keyframes toast-progress {
  from {
    transform: scaleX(1);
  }
  to {
    transform: scaleX(0);
  }
}

/* Transitions */
.toast-enter-active {
  transition: all 0.3s ease-out;
}

.toast-leave-active {
  transition: all 0.3s ease-in;
}

.toast-enter-from {
  transform: translateX(100%);
  opacity: 0;
}

.toast-leave-to {
  transform: translateX(100%);
  opacity: 0;
}

/* Responsive */
@media (max-width: 768px) {
  .toast-container {
    top: 1rem;
    right: 1rem;
    left: 1rem;
  }
  
  .toast-wrapper {
    max-width: none;
  }
  
  .toast {
    padding: 0.875rem;
  }
  
  .toast-title {
    font-size: 0.85rem;
  }
  
  .toast-message {
    font-size: 0.8rem;
  }
}
</style>

<!-- src/plugins/toast.js -->
<script>
// Toast Plugin for Vue.js
export default {
  install(app) {
    const toast = {
      success(message, title = '', duration = 5000) {
        app.config.globalProperties.$root.$emit('showToast', {
          type: 'success',
          title,
          message,
          duration
        })
      },
      
      error(message, title = '', duration = 7000) {
        app.config.globalProperties.$root.$emit('showToast', {
          type: 'error',
          title,
          message,
          duration
        })
      },
      
      warning(message, title = '', duration = 6000) {
        app.config.globalProperties.$root.$emit('showToast', {
          type: 'warning',
          title,
          message,
          duration
        })
      },
      
      info(message, title = '', duration = 5000) {
        app.config.globalProperties.$root.$emit('showToast', {
          type: 'info',
          title,
          message,
          duration
        })
      }
    }
    
    // Make toast available globally
    app.config.globalProperties.$toast = toast
    app.provide('toast', toast)
  }
}
</script>

<!-- Usage Examples -->
<!--
// In your main.js
import { createApp } from 'vue'
import App from './App.vue'
import ToastPlugin from './plugins/toast.js'
import ToastNotification from './components/common/ToastNotification.vue'

const app = createApp(App)
app.use(ToastPlugin)
app.component('ToastNotification', ToastNotification)

// Add ToastNotification component to your App.vue template:
<template>
  <div id="app">
    <router-view />
    <ToastNotification />
  </div>
</template>

// Usage in components:
// Success message
this.$toast.success('Journal entry created successfully')

// Error message
this.$toast.error('Failed to save journal entry', 'Save Error')

// Warning message
this.$toast.warning('Entry is not balanced')

// Info message
this.$toast.info('Processing your request...')

// In Composition API:
import { inject } from 'vue'

export default {
  setup() {
    const toast = inject('toast')
    
    const handleSuccess = () => {
      toast.success('Operation completed successfully!')
    }
    
    return { handleSuccess }
  }
}
-->