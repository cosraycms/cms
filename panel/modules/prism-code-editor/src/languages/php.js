import { languageMap } from "../index.js";
import { getClosestToken } from "../utils/index.js";
import { autoCloseTags, clikeComment, htmlAutoIndent, markupComment, voidTags, xmlOpeningTag, } from "./shared/index.js";
languageMap.php = {
    comments: clikeComment,
    getComments: (editor, position) => {
        if (getClosestToken(editor, ".php", 0, 0, position))
            return clikeComment;
        return markupComment;
    },
    autoIndent: htmlAutoIndent(xmlOpeningTag, voidTags),
    autoCloseTags: ([start, end], value, editor) => {
        return !value.includes("<?") || getClosestToken(editor, ".php", 0, 0, start)
            ? ""
            : autoCloseTags(editor, start, end, value, xmlOpeningTag, voidTags);
    },
};
