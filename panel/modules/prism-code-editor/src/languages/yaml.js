import { languageMap } from "../core.js";
import { bracketIndenting } from "./shared/index.js";
languageMap.yml = languageMap.yaml = bracketIndenting({ line: "#" });
