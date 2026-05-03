#!/usr/bin/env bash
# Export Overleaf data (mongo + binary files) into a tarball for import into the docs app.
# Run on the host that has the overleaf docker compose stack (e.g. irishof.cloud).
#
# Output: /tmp/overleaf-export.tar.gz containing:
#   users.json        — array of users (id, email, first_name, last_name)
#   projects.json     — array of active (non-deleted) projects with rootFolder tree
#   docs/{doc_id}.json    — doc content as { lines: [...] }
#   files/{project_id}/{file_id}  — binary files copied from overleaf filestore

set -euo pipefail

OUT=/tmp/overleaf-export
MONGO_CONTAINER=${MONGO_CONTAINER:-mongo}
SL_CONTAINER=${SL_CONTAINER:-sharelatex}
DB=${DB:-sharelatex}
SL_FILESTORE=${SL_FILESTORE:-/var/lib/overleaf/data/user_files}

rm -rf "$OUT"
mkdir -p "$OUT/docs" "$OUT/files"

echo "==> dumping users"
docker exec "$MONGO_CONTAINER" mongosh --quiet "$DB" --eval '
  EJSON.stringify(db.users.find({}, {email:1, first_name:1, last_name:1}).toArray())
' | tail -n +1 > "$OUT/users.json"

echo "==> dumping projects (active only)"
docker exec "$MONGO_CONTAINER" mongosh --quiet "$DB" --eval '
  EJSON.stringify(db.projects.find({active: {$ne: false}}, {
    name:1, owner_ref:1, collaberator_refs:1, readOnly_refs:1,
    publicAccesLevel:1, rootFolder:1, lastUpdated:1
  }).toArray())
' > "$OUT/projects.json"

echo "==> collecting doc ids referenced by projects"
docker exec "$MONGO_CONTAINER" mongosh --quiet "$DB" --eval '
  function walk(folders, out) {
    if (!folders) return;
    for (const f of folders) {
      for (const d of (f.docs||[])) out.push(String(d._id));
      walk(f.folders, out);
    }
  }
  const out = [];
  db.projects.find({active: {$ne: false}}, {rootFolder:1}).forEach(p => walk(p.rootFolder, out));
  print(out.join("\n"));
' | tr -d '\r' | grep -E '^[a-f0-9]{24}$' > "$OUT/doc-ids.txt"

DOC_COUNT=$(wc -l < "$OUT/doc-ids.txt")
echo "==> dumping $DOC_COUNT docs"

# Dump all referenced docs in chunks (mongosh has no cat(); pass ids as a JS array)
> "$OUT/docs.ndjson"
split -l 200 "$OUT/doc-ids.txt" "$OUT/.chunk-"
for chunk in "$OUT"/.chunk-*; do
  IDS=$(awk '{printf "ObjectId(\"%s\"),", $1}' "$chunk" | sed 's/,$//')
  docker exec "$MONGO_CONTAINER" mongosh --quiet "$DB" --eval "
    db.docs.find({_id:{\$in:[$IDS]}}, {lines:1}).forEach(d => {
      print(JSON.stringify({_id: String(d._id), lines: d.lines || []}));
    });
  " >> "$OUT/docs.ndjson"
done
rm -f "$OUT"/.chunk-*

# Split ndjson into per-doc files
python3 - "$OUT" <<'PY'
import json, os, sys
out = sys.argv[1]
with open(f"{out}/docs.ndjson") as f:
    for line in f:
        line = line.strip()
        if not line: continue
        d = json.loads(line)
        with open(f"{out}/docs/{d['_id']}.json", "w") as g:
            json.dump({"lines": d["lines"]}, g)
PY
rm "$OUT/docs.ndjson"

echo "==> collecting binary file refs"
docker exec "$MONGO_CONTAINER" mongosh --quiet "$DB" --eval '
  function walk(folders, pid, out) {
    if (!folders) return;
    for (const f of folders) {
      for (const ref of (f.fileRefs||[])) out.push(pid + " " + String(ref._id));
      walk(f.folders, pid, out);
    }
  }
  const out = [];
  db.projects.find({active: {$ne: false}}, {rootFolder:1}).forEach(p => walk(p.rootFolder, String(p._id), out));
  print(out.join("\n"));
' | tr -d '\r' | grep -E '^[a-f0-9]{24} [a-f0-9]{24}$' > "$OUT/file-refs.txt"

FILE_COUNT=$(wc -l < "$OUT/file-refs.txt")
echo "==> copying $FILE_COUNT binary files from $SL_CONTAINER:$SL_FILESTORE"
while read -r pid fid; do
  src="$SL_FILESTORE/${pid}_${fid}"
  dst_dir="$OUT/files/$pid"
  mkdir -p "$dst_dir"
  if docker exec "$SL_CONTAINER" test -f "$src"; then
    docker cp "$SL_CONTAINER:$src" "$dst_dir/$fid" 2>/dev/null || echo "  ! missing $src"
  else
    echo "  ! not found: $src"
  fi
done < "$OUT/file-refs.txt"

echo "==> tarballing"
tar -czf /tmp/overleaf-export.tar.gz -C /tmp overleaf-export
echo "DONE: /tmp/overleaf-export.tar.gz ($(du -h /tmp/overleaf-export.tar.gz | cut -f1))"
