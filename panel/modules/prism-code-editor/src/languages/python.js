import { languageMap } from "../core.js";
import { bracketIndenting } from "./shared/index.js";
languageMap.rpy =
    languageMap.renpy =
        languageMap.py =
            languageMap.python =
                bracketIndenting({ line: "#" }, /[([{][^)\]}]*$|:\s*$/);
