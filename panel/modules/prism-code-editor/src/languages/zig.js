import { languageMap } from "../index.js";
import { bracketIndenting } from "./shared/index.js";
languageMap.zig = bracketIndenting({
    line: "//",
});
