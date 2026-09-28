import { languageMap } from "../core.js";
import { bracketIndenting } from "./shared/index.js";
languageMap.plsql = languageMap.sql = bracketIndenting({
    line: "--",
    block: ["/*", "*/"],
});
