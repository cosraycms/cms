import { languageMap } from "../index.js";
import { markupTemplateLang } from "./shared/index.js";
languageMap.mustache = languageMap.hbs = markupTemplateLang("handlebars", {
    block: ["{{!", "}}"],
});
