/**
 * Provides the texts for the backend's Msg keys from contract/messages (E12): those of the core and those the plugins
 * bring along (V10, joined by the backend's contract build, each in its namespace source.<id>. or layer.<id>.).
 */
import de from '@contract/messages/de.json';
import pluginsDe from '@contract/messages/plugins.de.json';

/** A text, or for a count its plural forms (texts of the plugins). */
export type MessageText = string | { one: string; other: string };

export const contractMessagesDe: Readonly<Record<string, MessageText>> = {
  ...de,
  ...(pluginsDe as Record<string, MessageText>),
};
