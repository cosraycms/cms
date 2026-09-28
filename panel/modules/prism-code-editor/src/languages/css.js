import { languageMap } from "../core.js";
import { bracketIndenting, clikeComment } from "./shared/index.js";
languageMap.css = bracketIndenting({
    block: ["/*", "*/"],
});
languageMap.less = languageMap.scss = bracketIndenting();
languageMap.sass = {
    comments: clikeComment,
    // Let's not bother with auto-indenting for sass
};
