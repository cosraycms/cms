import { languageMap } from "../index.js";
import { bracketIndenting } from "./shared/index.js";
languageMap.sh = languageMap.shell = languageMap.bash = bracketIndenting({ line: "#" });
