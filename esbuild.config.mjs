import * as esbuild from 'esbuild';
import path from 'path';
import { glob } from 'glob';
import { fileURLToPath } from 'url';
import postcss from 'postcss';
import tailwindcss from '@tailwindcss/postcss'; // Tailwind v4 전용 포스트CSS 플러그인
import autoprefixer from 'autoprefixer';
import postcssImport from 'postcss-import';
import * as sass from 'sass';
import fs from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const skin = 'basic';
const isWatch = process.argv.includes('--watch');
const shouldMinify = !isWatch; // 개발(watch) 중엔 false, 배포 빌드 시엔 true

const jsFiles = glob.sync(`resources/skins/${skin}/js/**/*.ts`);
const entryPoints = jsFiles.filter(file => !path.basename(file).endsWith('global123.ts') && !file.endsWith('vendor.ts'));

// 2. 공통 벤더 파일 빌드 설정 (jQuery, Axios 등 묶음)
const vendorConfig = {
  entryPoints: { vendor: `./resources/skins/${skin}/js/vendor.ts` },
  bundle: true,
  minify: shouldMinify, // true 일 때 공백/줄바꿈 제거 및 변수명 압축
  sourcemap: isWatch,   // 개발 중에만 소스맵 생성
  format: 'iife',       // 브라우저 전역에 바로 등록되도록 설정
  outdir: `public_html/dist/${skin}/js`,
};

// 3. 개별 페이지용 TS 파일 빌드 설정
const config = {
  entryPoints: entryPoints,
  bundle: true,
  minify: shouldMinify,
  sourcemap: isWatch,
  format: 'esm',
  target: 'esnext',
  outdir: `public_html/dist/${skin}/js`,
  alias: {
    '@': path.resolve(__dirname, './src'),
    '@css': path.resolve(__dirname, './resources/skins', skin, 'css'),
    '@commons': path.resolve(__dirname, './resources/skins', skin, 'js/commons'),
  },
  external: ['jquery', 'axios', 'lodash', '@toast-ui/grid'],
  outbase: `resources/skins/${skin}/js`,

  // ⚡ [여기 추가] 폰트와 이미지를 깨지지 않게 원본 그대로 복사하도록 설정
  loader: {
    '.woff2': 'file',
    '.woff': 'file',
    '.ttf': 'file',
    '.png': 'file',
    '.jpg': 'file',
    '.jpeg': 'file',
    '.gif': 'file',
    '.svg': 'file'
  }
};

// 4. 스타일 빌드 함수 (TailwindCSS v4 + Sass + CSS Minify)
async function buildStyles() {
  const scssFiles = ['common', 'admin', 'test'];
  const outDir = `public_html/dist/${skin}/css`;

  if (!fs.existsSync(outDir)) {
    fs.mkdirSync(outDir, { recursive: true });
  }

  for (const name of scssFiles) {
    const srcPath = `resources/skins/${skin}/css/${name}.scss`;
    if (fs.existsSync(srcPath)) {
      try {
        // Step A: Sass 컴파일 진행 (중첩 문법 해제)
        const sassResult = sass.compile(srcPath, {
          style: shouldMinify ? 'compressed' : 'expanded'
        });

        // Step B: PostCSS 가동 (Tailwind v4 적용)
        const postcssResult = await postcss([
          postcssImport(),
          tailwindcss(),
          autoprefixer()
        ]).process(sassResult.css, {
          from: srcPath,
          to: `${outDir}/${name}.css`
        });

        // Step C: 배포 빌드일 때 한 번 더 바짝 CSS 압축
        let finalCss = postcssResult.css;
        if (shouldMinify) {
          const minified = await esbuild.transform(finalCss, { loader: 'css', minify: true });
          finalCss = minified.code;
        }

        fs.writeFileSync(`${outDir}/${name}.css`, finalCss);
        console.log(`🎨 ${name}.scss -> 스타일 빌드 성공! (Minify: ${shouldMinify})`);

      } catch (err) {
        console.error(`[스타일 에러] ${name}.scss 변환 실패:\n`, err.message);
      }
    }
  }
}

// 5. 통합 실행 및 감시(Watch) 로직
async function run() {
  // 벤더 진입점 파일 자생적으로 생성 검사
  const vendorPath = `./resources/skins/${skin}/js/vendor.ts`;
  if (!fs.existsSync(vendorPath)) {
    const jsDir = `./resources/skins/${skin}/js`;
    if (!fs.existsSync(jsDir)) fs.mkdirSync(jsDir, { recursive: true });
    fs.writeFileSync(vendorPath, `
import $ from 'jquery';
import axios from 'axios';
import _ from 'lodash';

declare global {
  interface Window {
    $: typeof $;
    jQuery: typeof $;
    axios: typeof axios;
    _: typeof _;
  }
}

window.$ = window.jQuery = $;
window.axios = axios;
window._ = _;
    `.trim());
  }

  // ⚡ [핵심 추가] 원본 소스 폴더의 assets(폰트, 이미지 등)를 dist 폴더로 안전하게 강제 복사하는 함수
  function syncAssets() {
    const srcAssets = `resources/skins/${skin}/assets`;
    const distAssets = `public_html/dist/${skin}/assets`;

    console.log("\n");

    if (fs.existsSync(srcAssets)) {
      // Node.js 기본 기능으로 assets 폴더 내 내용물을 날것 그대로 깨짐 없이 안전하게 복사합니다.
      fs.cpSync(srcAssets, distAssets, { recursive: true, force: true });
      console.log(`📦 [Assets Sync] 폰트 및 이미지 파일 동기화 완료!`);
    } else {
      console.warn(`⚠️ [Assets 경고] ${srcAssets} 폴더를 찾을 수 없습니다. 경로를 확인해 주세요.`);
    }
  }

  // 빌드 시작할 때 파일들 먼저 안전하게 복사 실행!
  syncAssets();
  await esbuild.build(vendorConfig);
  await buildStyles();

  if (isWatch) {
    console.log('⚡ Esbuild 상시 감시(Watch) 모드 시작...');

    const ctx = await esbuild.context({
      ...config,
      plugins: [{
        name: 'watch-plugin',
        setup(build) {
          build.onEnd(result => {
            if (result.errors.length === 0) {
              console.log(`✨ TS 빌드 완료 (${new Date().toLocaleTimeString()})`);
            }
          });
        }
      }]
    });

    await ctx.watch();

    // 파일 감시 대상 지정 (Sass, TS, HTML 변경 시 스타일 리빌드 및 에셋 재복사)
    fs.watch(`resources/skins/${skin}`, { recursive: true }, async (eventType, filename) => {
      if (filename && (filename.endsWith('.scss') || filename.endsWith('.ts') || filename.endsWith('.html') || filename.includes('assets'))) {
        console.log(`[변경 감지: ${filename}] 업데이트 중...`);
        syncAssets(); // ⚡ 개발 중에 폰트나 이미지가 추가되어도 바로 복사되도록 동기화
        await buildStyles();
      }
    });

  } else {
    console.log('📦 배포용 전체 빌드 중...');
    await esbuild.build(config);
    console.log('🎉 전체 빌드 성공!');
  }
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});
