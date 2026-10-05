/**
 * Shows the icon of a layer (T-06) by the icon name in its description; the icons in use are generated (L-D8).
 */
import { Info } from 'lucide-react';
import { layerIcons } from '../../generated/layer-icons';

export function LayerIcon({ icon, size = 16 }: { icon: string; size?: number }) {
  const Icon = layerIcons[icon] ?? Info;
  return <Icon size={size} aria-hidden="true" focusable="false" />;
}
