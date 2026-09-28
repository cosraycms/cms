import { languageMap } from "../core.js";
import { bracketIndenting } from "./shared/index.js";
languageMap.crystal = languageMap.rb = languageMap.ruby = bracketIndenting({ line: "#", block: ["=begin", "=end"] });
