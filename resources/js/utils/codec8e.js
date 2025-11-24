export function parseCodec8Extended(hex) {
  const clean = String(hex).replace(/\s+/g, '').toLowerCase();
  if (!/^[0-9a-f]+$/.test(clean)) throw new Error('Invalid hex');
  const bytes = new Uint8Array(clean.length / 2);
  for (let i = 0; i < clean.length; i += 2) bytes[i / 2] = parseInt(clean.slice(i, i + 2), 16);
  const dv = new DataView(bytes.buffer);
  let off = 0;
  if (dv.getUint32(off) === 0) off += 4;
  if (bytes.length - off < 6) throw new Error('Too short');
  const dataLen = dv.getUint32(off); off += 4;
  const codecId = dv.getUint8(off); off += 1;
  const recordCount = dv.getUint8(off); off += 1;
  const records = [];
  for (let r = 0; r < recordCount; r++) {
    const tsHi = dv.getUint32(off); const tsLo = dv.getUint32(off + 4); off += 8;
    const timestampMs = Number((BigInt(tsHi) << 32n) | BigInt(tsLo));
    const priority = dv.getUint8(off); off += 1;
    const lonRaw = dv.getInt32(off); off += 4;
    const latRaw = dv.getInt32(off); off += 4;
    const altitude = dv.getInt16(off); off += 2;
    const angle = dv.getUint16(off); off += 2;
    const satellites = dv.getUint8(off); off += 1;
    const speed = dv.getUint16(off); off += 2;
    const eventId = dv.getUint16(off); off += 2;
    const totalIo = dv.getUint16(off); off += 2;
    const io = {};
    const readGroup = (size) => {
      const count = dv.getUint16(off); off += 2;
      for (let i = 0; i < count; i++) {
        const id = dv.getUint16(off); off += 2;
        let val;
        if (size === 1) { val = dv.getUint8(off); }
        else if (size === 2) { val = dv.getUint16(off); }
        else if (size === 4) { val = dv.getUint32(off); }
        else if (size === 8) {
          const hi = dv.getUint32(off), lo = dv.getUint32(off + 4);
          val = Number((BigInt(hi) << 32n) | BigInt(lo));
        }
        off += size;
        io[id] = val;
      }
    };
    readGroup(1);
    readGroup(2);
    readGroup(4);
    readGroup(8);
    records.push({
      codecId,
      time: new Date(timestampMs).toISOString(),
      timestampMs,
      priority,
      lat: latRaw / 10000000,
      lon: lonRaw / 10000000,
      altitude,
      angle,
      satellites,
      speed,
      eventId,
      totalIo,
      io,
    });
  }
  return records;
}
