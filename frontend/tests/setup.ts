/**
 * Shared test setup: DOM matchers for React Testing Library and stand-ins for browser functions that jsdom lacks
 * (Radix measures sizes; Radix Select scrolls items into view and captures the pointer).
 */
import '@testing-library/jest-dom/vitest';

if (typeof window !== 'undefined' && typeof window.ResizeObserver === 'undefined') {
  window.ResizeObserver = class {
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
  };
}

if (typeof window !== 'undefined') {
  const proto = window.HTMLElement.prototype;
  proto.scrollIntoView ??= function scrollIntoView(): void {};
  proto.hasPointerCapture ??= function hasPointerCapture(): boolean {
    return false;
  };
  proto.releasePointerCapture ??= function releasePointerCapture(): void {};
}
