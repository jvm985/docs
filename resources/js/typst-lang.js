import { StreamLanguage } from "@codemirror/language";

export const typstMode = {
  name: "typst",
  token(stream) {
    if (stream.eatSpace()) return null;

    // Headings: = Head, == Sub
    if (stream.sol() && stream.match(/=+ /)) {
      stream.skipToEnd();
      return "heading";
    }

    // Comments: // or /* */
    if (stream.match("//")) {
      stream.skipToEnd();
      return "comment";
    }
    if (stream.match("/*")) {
      while (!stream.eol()) {
        if (stream.match("*/")) break;
        stream.next();
      }
      return "comment";
    }

    // Functions/Scripting: #let, #set, #show, #for, #if, etc.
    if (stream.match(/#(let|set|show|for|if|else|import|include|return|while|break|continue|as|in|not|and|or)\b/)) {
      return "keyword";
    }
    
    // Custom functions: #myfunc
    if (stream.match(/#[a-zA-Z0-9_]+/)) return "function";

    // Strings: "..."
    if (stream.match(/"/)) {
      while (!stream.eol()) {
        if (stream.match(/"/)) break;
        if (stream.next() == "\\") stream.next();
      }
      return "string";
    }

    // Bold: *text*
    if (stream.match(/\*[^* \n][^*]*\*/)) return "strong";
    
    // Italic: _text_
    if (stream.match(/_[^_ \n][^_]*_/)) return "emphasis";

    // Monospace: `text`
    if (stream.match(/`[^`]*`/)) return "monospace";

    // List items: - or +
    if (stream.sol() && stream.match(/[-+] /)) return "list";

    // Numbers
    if (stream.match(/[0-9]+(\.[0-9]+)?(pt|em|cm|mm|in|%)?/)) return "number";

    stream.next();
    return null;
  }
};

export const typstLanguage = StreamLanguage.define(typstMode);
