import autoprefixer from 'autoprefixer';

function replaceColorAdjust() {
    return {
        postcssPlugin: 'replace-color-adjust',
        Declaration(decl) {
            if (decl.prop === 'color-adjust') {
                decl.prop = 'print-color-adjust';
            }
        },
    };
}
replaceColorAdjust.postcss = true;

export default {
    plugins: [
        replaceColorAdjust,
        autoprefixer,
    ],
};
