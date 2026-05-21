import { Node, mergeAttributes } from '@tiptap/core';

export const SurveyVideoNode = Node.create({
  name: 'surveyVideo',
  group: 'block',
  atom: true,

  addAttributes() {
    return {
      src: { default: null },
      provider: { default: 'youtube' },
    };
  },

  parseHTML() {
    return [
      {
        tag: 'div.survey-video',
        getAttrs: (dom) => {
          const iframe = (dom as HTMLElement).querySelector('iframe');
          return {
            src: iframe?.getAttribute('src') ?? null,
            provider: iframe?.dataset?.provider ?? 'youtube',
          };
        },
      },
    ];
  },

  renderHTML({ HTMLAttributes }) {
    return [
      'div',
      { class: 'survey-video' },
      [
        'iframe',
        mergeAttributes({
          src: HTMLAttributes.src,
          'data-provider': HTMLAttributes.provider,
          allowfullscreen: 'true',
          loading: 'lazy',
          referrerpolicy: 'strict-origin-when-cross-origin',
          frameborder: '0',
        }),
      ],
    ];
  },
});
