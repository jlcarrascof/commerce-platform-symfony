<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps<{
  modelValue: boolean
  title: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const dialogRef = ref<HTMLElement | null>(null)
const titleId = `modal-title-${Math.random().toString(36).slice(2, 9)}`

function close(): void {
  emit('update:modelValue', false)
}

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    close()
    return
  }

  if (event.key !== 'Tab' || !dialogRef.value) {
    return
  }

  const focusable = dialogRef.value.querySelectorAll<HTMLElement>(
    'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])',
  )
  if (focusable.length === 0) {
    return
  }

  const first = focusable[0]
  const last = focusable[focusable.length - 1]

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

let previouslyFocusedElement: HTMLElement | null = null

watch(
  () => props.modelValue,
  async (isOpen) => {
    if (isOpen) {
      previouslyFocusedElement = document.activeElement as HTMLElement | null
      document.addEventListener('keydown', handleKeydown)
      await nextTick()
      const firstField = dialogRef.value?.querySelector<HTMLElement>(
        'input, select, textarea, button:not([data-modal-close])',
      )
      firstField?.focus()
    } else {
      document.removeEventListener('keydown', handleKeydown)
      previouslyFocusedElement?.focus()
    }
  },
)

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="base-modal__overlay" @mousedown.self="close">
      <div
        ref="dialogRef"
        class="base-modal"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
      >
        <div class="base-modal__header">
          <h2 :id="titleId" class="base-modal__title">{{ title }}</h2>
          <button
            type="button"
            class="base-modal__close"
            data-modal-close
            :aria-label="t('common.close')"
            @click="close"
          >
            ✕
          </button>
        </div>
        <div class="base-modal__body">
          <slot />
        </div>
        <div v-if="$slots.footer" class="base-modal__footer">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.base-modal__overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-md);
  z-index: 100;
}

.base-modal {
  width: 100%;
  max-width: 480px;
  max-height: calc(100vh - 2 * var(--space-xl));
  display: flex;
  flex-direction: column;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.4);
}

.base-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-lg) var(--space-lg) var(--space-md);
  border-bottom: 1px solid var(--color-border);
}

.base-modal__title {
  font-size: 18px;
  margin: 0;
}

.base-modal__close {
  font-family: var(--font-sans);
  font-size: 16px;
  line-height: 1;
  padding: var(--space-xs);
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  border-radius: var(--radius-sm);
}

.base-modal__close:hover {
  color: var(--color-text);
  background: var(--color-bg);
}

.base-modal__body {
  padding: var(--space-lg);
  overflow-y: auto;
}

.base-modal__footer {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-sm);
  padding: var(--space-md) var(--space-lg) var(--space-lg);
  border-top: 1px solid var(--color-border);
}
</style>
