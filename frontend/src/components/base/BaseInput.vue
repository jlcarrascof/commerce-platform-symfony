<script setup lang="ts">
withDefaults(
  defineProps<{
    modelValue?: string
    type?: string
    required?: boolean
    placeholder?: string
  }>(),
  { type: 'text' },
)

defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <input
    class="base-input"
    :type="type"
    :value="modelValue"
    :required="required"
    :placeholder="placeholder"
    @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
  />
</template>

<style scoped>
.base-input {
  font-family: var(--font-sans);
  font-size: 14px;
  padding: var(--space-sm) var(--space-md);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-bg);
  color: var(--color-text);
}

.base-input:focus {
  outline: none;
  border-color: var(--color-accent-500);
  box-shadow: 0 0 0 3px var(--color-accent-100);
}
</style>
