/**
 * The note of a layer with one line per source below it, saying what the source covers (layers as plugins, L-D7).
 */
export function LayerNote({ note, coverage }: { note: string | null; coverage: readonly string[] }) {
  return (
    <>
      {note === null ? null : <p className="layer-note">{note}</p>}
      {coverage.map((line) => (
        <p key={line} className="layer-note">
          {line}
        </p>
      ))}
    </>
  );
}
