import { languageMap } from "../core.js";
import { bracketIndenting } from "./shared/index.js";
languageMap.fsharp = bracketIndenting({ line: "//", block: ["(*", "*)"] });
