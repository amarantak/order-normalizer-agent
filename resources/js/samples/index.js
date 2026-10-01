// Ejemplos crudos de la demo: una sola lista con el source, la etiqueta y el JSON de cada uno.
// - source: el que valida el endpoint (pos1/pos2/pos3).
// - label: qué demuestra el caso (sin marcas ni países, decisión del chat 6).
// - rawOrder: el objeto que se manda al endpoint.
// - rawText: el archivo tal cual (?raw de Vite), para mostrarlo exactamente como lo mandó el POS.
import pos1Clean from './pos1-clean.json';
import pos1CleanText from './pos1-clean.json?raw';
import pos2TipAndCoverCharge from './pos2-tip-and-cover-charge.json';
import pos2TipAndCoverChargeText from './pos2-tip-and-cover-charge.json?raw';
import pos3Malformed from './pos3-malformed.json';
import pos3MalformedText from './pos3-malformed.json?raw';
import pos1InconsistentTotal from './pos1-inconsistent-total.json';
import pos1InconsistentTotalText from './pos1-inconsistent-total.json?raw';

export const samples = [
    { id: 'pos1-clean', source: 'pos1', label: 'POS 1 · Clean', rawOrder: pos1Clean, rawText: pos1CleanText },
    { id: 'pos2-tip-and-cover-charge', source: 'pos2', label: 'POS 2 · Tip & cover charge', rawOrder: pos2TipAndCoverCharge, rawText: pos2TipAndCoverChargeText },
    { id: 'pos3-malformed', source: 'pos3', label: 'POS 3 · Malformed', rawOrder: pos3Malformed, rawText: pos3MalformedText },
    { id: 'pos1-inconsistent-total', source: 'pos1', label: 'Inconsistent total', rawOrder: pos1InconsistentTotal, rawText: pos1InconsistentTotalText },
];