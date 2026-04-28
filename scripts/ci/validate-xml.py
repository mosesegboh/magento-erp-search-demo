#!/usr/bin/env python3
from pathlib import Path
import sys
import xml.etree.ElementTree as ET

ROOT_DIR = Path(__file__).resolve().parents[2]
SEARCH_ROOTS = [
    ROOT_DIR / "src" / "app" / "code" / "Portfolio",
]


def discover_xml_files() -> list[Path]:
    files: list[Path] = []

    for root in SEARCH_ROOTS:
        if not root.exists():
            continue

        for path in root.rglob("*.xml"):
            if "frontend/node_modules" in path.as_posix():
                continue
            files.append(path)

    return sorted(files)


def main() -> int:
    failures: list[str] = []
    files = discover_xml_files()

    for path in files:
        try:
            ET.parse(path)
        except ET.ParseError as exc:
            failures.append(f"{path.relative_to(ROOT_DIR)}: {exc}")

    if failures:
        print("XML validation failed:", file=sys.stderr)
        print("\n".join(failures), file=sys.stderr)
        return 1

    print(f"XML OK: {len(files)} files")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
