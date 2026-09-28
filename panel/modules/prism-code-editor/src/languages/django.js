import { languageMap } from "../index.js";
import { markupTemplateLang } from "./shared/index.js";
languageMap.jinja2 = markupTemplateLang("django", {
    block: ["{#", "#}"],
});
