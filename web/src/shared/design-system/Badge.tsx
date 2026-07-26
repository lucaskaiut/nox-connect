import { useMemo, type ComponentProps, type ReactNode } from 'react'
import { cn } from '@/shared/utils/cn'

type BadgeVariant = 'neutral' | 'primary' | 'success' | 'warning' | 'danger'

const VARIANTS: Record<BadgeVariant, string> = {
  neutral: 'bg-surface-2 text-muted',
  primary: 'bg-primary-soft text-primary',
  success: 'bg-success-soft text-success',
  warning: 'bg-warning-soft text-warning',
  danger: 'bg-danger-soft text-danger',
}

function hexToRgb(hex: string): [number, number, number] | null {
  const match = hex.replace('#', '').match(/^([0-9a-f]{3}|[0-9a-f]{6})$/i)
  if (!match) return null
  let r: number, g: number, b: number
  if (match[1].length === 3) {
    r = parseInt(match[1][0] + match[1][0], 16)
    g = parseInt(match[1][1] + match[1][1], 16)
    b = parseInt(match[1][2] + match[1][2], 16)
  } else {
    r = parseInt(match[1].substring(0, 2), 16)
    g = parseInt(match[1].substring(2, 4), 16)
    b = parseInt(match[1].substring(4, 6), 16)
  }
  return [r, g, b]
}

function luminance(r: number, g: number, b: number): number {
  const toLinear = (c: number) => {
    const s = c / 255
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
  }
  return 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b)
}

function contrastingColor(bgColor: string): string | null {
  const rgb = hexToRgb(bgColor)
  if (!rgb) return null
  return luminance(...rgb) > 0.5 ? '#1a1a1a' : '#ffffff'
}

export function Badge({
  variant = 'neutral',
  className,
  style,
  children,
  ...props
}: {
  variant?: BadgeVariant
  className?: string
  children: ReactNode
} & ComponentProps<'span'>) {
  const bgColor = typeof style?.backgroundColor === 'string' ? style.backgroundColor : null
  const textColor = useMemo(() => (bgColor ? contrastingColor(bgColor) : null), [bgColor])

  const mergedStyle = textColor
    ? { ...style, backgroundColor: bgColor, color: textColor }
    : style

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
        VARIANTS[variant],
        className,
      )}
      style={mergedStyle}
      {...props}
    >
      {children}
    </span>
  )
}
