<script setup lang="ts">
withDefaults(
  defineProps<{
    modelValue?: string
    type?: string
    required?: boolean
    placeholder?: string
    minlength?: number
    disabled?: boolean
  }>(),
  { type: 'text' },
)

defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <div class="base-input-wrapper">
    <input
      class="base-input"
      :class="{ 'base-input--with-icon': !!$slots.icon }"
      :type="type"
      :value="modelValue"
      :required="required"
      :placeholder="placeholder"
      :minlength="minlength"
      :disabled="disabled"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <span v-if="$slots.icon" class="base-input__icon">
      <slot name="icon" />
    </span>
  </div>
</template>

<style scoped>
.base-input-wrapper {
  position: relative;
  display: flex;
}

.base-input {
  width: 100%;
  font-family: var(--font-sans);
  font-size: 14px;
  padding: var(--space-sm) var(--space-md);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-bg);
  color: var(--color-text);
}

.base-input--with-icon {
  padding-right: calc(var(--space-md) + 22px);
}

.base-input:focus {
  outline: none;
  border-color: var(--color-accent-500);
  box-shadow: 0 0 0 3px var(--color-accent-100);
}

.base-input:disabled {
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: not-allowed;
}

.base-input__icon {
  position: absolute;
  top: 50%;
  right: var(--space-sm);
  transform: translateY(-50%);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-muted);
}
</style>
