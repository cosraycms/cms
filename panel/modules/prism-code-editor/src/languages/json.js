import { languageMap } from "../core.js";
import { bracketIndenting } from "./shared/index.js";
languageMap.json = languageMap.json5 = languageMap.jsonp = bracketIndenting();
