import * as RadixSelect from '@radix-ui/react-select'

/** Radix items cannot use "" as a value, so "" (e.g. "All genres") is mapped to this sentinel internally. */
const EMPTY = '__all__'

export interface SelectOption {
  value: string
  label: string
}

interface SelectProps {
  value: string
  onValueChange: (value: string) => void
  options: SelectOption[]
  'aria-label'?: string
  'aria-labelledby'?: string
  className?: string
}

/**
 * Themed dropdown (Radix Select): keyboard navigation, type-ahead and listbox semantics
 * come from Radix; it also renders a hidden native <select> for form/autofill support.
 */
export function Select({ value, onValueChange, options, className, ...aria }: SelectProps) {
  return (
    <RadixSelect.Root
      value={value === '' ? EMPTY : value}
      onValueChange={(v) => onValueChange(v === EMPTY ? '' : v)}
    >
      <RadixSelect.Trigger className={`select__trigger${className ? ` ${className}` : ''}`} {...aria}>
        <RadixSelect.Value />
        <RadixSelect.Icon className="select__icon">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden="true">
            <path d="m6 9 6 6 6-6" />
          </svg>
        </RadixSelect.Icon>
      </RadixSelect.Trigger>
      <RadixSelect.Portal>
        <RadixSelect.Content className="select__content" position="popper" sideOffset={6}>
          <RadixSelect.Viewport className="select__viewport">
            {options.map((o) => (
              <RadixSelect.Item key={o.value} value={o.value === '' ? EMPTY : o.value} className="select__item">
                <RadixSelect.ItemIndicator className="select__check">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5" />
                  </svg>
                </RadixSelect.ItemIndicator>
                <RadixSelect.ItemText>{o.label}</RadixSelect.ItemText>
              </RadixSelect.Item>
            ))}
          </RadixSelect.Viewport>
        </RadixSelect.Content>
      </RadixSelect.Portal>
    </RadixSelect.Root>
  )
}
