import { StreamLanguage } from "@codemirror/language";

export const typstMode = {
  name: "typst",
  token(stream) {
    if (stream.eatSpace()) return null;

    // Headings
    if (stream.sol() && stream.match(/=+ /)) {
      stream.skipToEnd();
      return "heading";
    }

    // Comments
    if (stream.match("//")) {
      stream.skipToEnd();
      return "comment";
    }
    if (stream.match("/*")) {
      while (!stream.eol() && !stream.match("*/")) {
        stream.next();
      }
      return "comment";
    }

    // Bold
    if (stream.match(/\*[^* \n][^*]*\*/)) return "strong";
    
    // Italic
    if (stream.match(/_[^_ \n][^_]*_/)) return "emphasis";

    // Monospace
    if (stream.match(/`[^`]*`/)) return "monospace";

    // Functions/Scripting
    if (stream.match(/#[a-zA-Z0-9_]+/)) return "keyword";

    // List items
    if (stream.sol() && stream.match(/[-+] /)) return "list";

    stream.next();
    return null;
  }
};

export const typstLanguage = StreamLanguage.define(typstMode);
