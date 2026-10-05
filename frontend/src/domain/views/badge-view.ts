/**
 * Describes the badge of an assessment or warning level with fixed color and text (T-07, T-08).
 */
export interface BadgeView {
  /** Fixed color; `null` = layer color (no threshold exceeded). */
  color: string | null;
  label: string;
  /** `unknown` is additionally drawn dashed (T-08). */
  dashed: boolean;
}
