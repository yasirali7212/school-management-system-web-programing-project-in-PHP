/**
 * Tailwind Configuration for EduSphere Academy Portal
 */
tailwind.config = {
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        "background": "#f7f9fe",
        "on-secondary": "#ffffff",
        "on-secondary-fixed-variant": "#40436c",
        "on-primary": "#ffffff",
        "error": "#ba1a1a",
        "outline": "#757686",
        "surface": "#f7f9fe",
        "error-container": "#ffdad6",
        "surface-tint": "#344dde",
        "secondary-fixed-dim": "#c0c3f4",
        "secondary-container": "#cbceff",
        "tertiary-fixed": "#e0e0ff",
        "on-primary-fixed": "#000e5e",
        "on-secondary-container": "#535681",
        "surface-container-low": "#f1f4f9",
        "on-surface": "#181c20",
        "inverse-primary": "#bbc3ff",
        "tertiary": "#4953b2",
        "secondary-fixed": "#e0e0ff",
        "on-tertiary-fixed": "#000767",
        "inverse-on-surface": "#eff1f6",
        "on-tertiary-container": "#fffcff",
        "primary": "#324bdc",
        "tertiary-fixed-dim": "#bdc2ff",
        "secondary": "#575b86",
        "on-surface-variant": "#444655",
        "surface-container": "#eceef3",
        "surface-container-high": "#e6e8ed",
        "primary-fixed-dim": "#bbc3ff",
        "on-error-container": "#93000a",
        "on-primary-container": "#fffdff",
        "outline-variant": "#c5c5d7",
        "surface-bright": "#f7f9fe",
        "on-background": "#181c20",
        "on-error": "#ffffff",
        "tertiary-container": "#626ccd",
        "on-tertiary-fixed-variant": "#323c9b",
        "on-tertiary": "#ffffff",
        "on-primary-fixed-variant": "#0f2fc6",
        "primary-container": "#4f66f6",
        "surface-dim": "#d8dadf",
        "surface-container-lowest": "#ffffff",
        "inverse-surface": "#2d3135",
        "surface-variant": "#e0e2e7",
        "primary-fixed": "#dfe0ff",
        "on-secondary-fixed": "#14173e",
        "surface-container-highest": "#e0e2e7"
      },
      borderRadius: {
        "DEFAULT": "0.25rem",
        "lg": "0.5rem",
        "xl": "0.75rem",
        "full": "9999px"
      },
      spacing: {
        "margin": "2rem",
        "gutter": "1.5rem",
        "space-xs": "0.25rem",
        "space-sm": "0.5rem",
        "space-xl": "2.5rem",
        "space-md": "1rem",
        "space-lg": "1.5rem",
        "gutter-sm": "1rem",
        "margin-sm": "1rem"
      },
      fontFamily: {
        "label-sm": ["Plus Jakarta Sans", "sans-serif"],
        "label-lg": ["Plus Jakarta Sans", "sans-serif"],
        "title-md": ["Plus Jakarta Sans", "sans-serif"],
        "headline-md": ["Plus Jakarta Sans", "sans-serif"],
        "display-lg": ["Plus Jakarta Sans", "sans-serif"],
        "headline-lg": ["Plus Jakarta Sans", "sans-serif"],
        "body-lg": ["Plus Jakarta Sans", "sans-serif"],
        "body-sm": ["Plus Jakarta Sans", "sans-serif"],
        "body-md": ["Plus Jakarta Sans", "sans-serif"],
        "headline-sm": ["Plus Jakarta Sans", "sans-serif"],
        "display-lg-mobile": ["Plus Jakarta Sans", "sans-serif"],
        "label-md": ["Plus Jakarta Sans", "sans-serif"]
      },
      fontSize: {
        "label-sm": ["11px", { lineHeight: "14px", fontWeight: "500" }],
        "label-lg": ["14px", { lineHeight: "20px", fontWeight: "600" }],
        "title-md": ["18px", { lineHeight: "26px", fontWeight: "600" }],
        "headline-md": ["24px", { lineHeight: "32px", fontWeight: "700" }],
        "display-lg": ["40px", { lineHeight: "48px", fontWeight: "800" }],
        "headline-lg": ["28px", { lineHeight: "36px", fontWeight: "700" }],
        "body-lg": ["16px", { lineHeight: "24px", fontWeight: "400" }],
        "body-sm": ["12px", { lineHeight: "18px", fontWeight: "400" }],
        "body-md": ["14px", { lineHeight: "20px", fontWeight: "400" }],
        "headline-sm": ["20px", { lineHeight: "28px", fontWeight: "600" }],
        "display-lg-mobile": ["32px", { lineHeight: "40px", fontWeight: "800" }],
        "label-md": ["12px", { lineHeight: "16px", fontWeight: "600" }]
      }
    }
  }
};

