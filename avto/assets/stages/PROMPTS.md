# Промпты для AI-видео этапов — сайт «Привоз авто из Европы» (RSI GARANT)

Машина: **серая Audi RS7 Sportback Mansory P1050** (кованый карбоновый обвес,
чёрные диски с оранжевым ободом, оранжевые суппорты), одна и та же на всех 5 сценах.
Логика: машина «путешествует» по экранам — каждая сцена показывает конкретный шаг
доставки: дилер → выкуп → автовоз → таможня → выдача у дома.
Референс-кадр: `ref-rs7-mansory.jpg` (лежит рядом в этой папке) — его загружаем в
Kling как стартовый кадр для КАЖДОЙ сцены, так машина не «плывёт».

## Порядок действий в Kling (klingai.com)

1. AI Videos → режим **Image to Video**.
2. Upload стартового кадра:
   - для stage-0 → `ref-rs7-mansory.jpg`;
   - для stage-1…4 → **последний кадр предыдущей сцены** (файлы
     `next-start-after-stage-N.jpg` в этой папке, я вытаскиваю их из каждого
     готового видео). Так экраны склеиваются в одно путешествие машины без
     скачков картинки.
3. В поле промпта вставить текст сцены (ниже, по одной, целиком).
4. Настройки: длительность **5s**, соотношение **16:9**, режим **Professional** (1080p).
5. Generate → скачать → переименовать в `stage-N.mp4` → положить в эту папку.
   Сайт подхватит видео сам, jpg остаётся постером и fallback.
6. На сцену закладывай 2–3 генерации, бери лучшую.

## Сцены

### stage-0.mp4 — Заявка и подбор (ШОУРУМ, обрезан до 4 сек) ✅ готово

Финальная версия: первый AI-дубль (шоурум дилера, наезд камеры),
обрезан до 4 сек, чтобы убрать концовку с наездом на фары (там картинка плыла).
Полная 5-сек версия лежит как `stage-0-showroom.mp4`, вариант с задом
(Ken Burns из фото) — `stage-0-rear.mp4`.

### stage-1.mp4 — Выкуп в Европе (ВЫЕЗЖАЕТ ОТ ДИЛЕРА)

> The same gray Mansory-tuned Audi RS7 Sportback from the reference image, forged carbon aero kit, black wheels with orange ring, orange brake calipers. Scene: early dusk outside a modern European car dealership with a glowing glass facade. The car starts parked at the dealership entrance, headlights turn on, then it slowly rolls forward out of the dealership driveway, wheels turning smoothly. Warm light from the showroom reflects and slides along the glossy gray body and carbon details as the car moves. Camera: low tracking shot at wheel height, smoothly following alongside the front fender, orange brake calipers visible through the spokes. No people, no text. Photorealistic, cinematic, shallow depth of field, 5 seconds

### stage-2.mp4 — Автовоз до границы (ТРАССА)

> The same gray Mansory-tuned Audi RS7 Sportback from the reference image, forged carbon aero kit, black wheels with orange ring. Scene: the car is secured with transport straps on the open upper deck of a premium car carrier truck driving steadily along a European highway at golden hour. Long shadows, warm low sun, green fields and white wind turbines in the distance, road signs in Europe. Camera: aerial drone shot, starts above and behind the truck, then smoothly catches up and orbits to a side view, showing the gray sports car strapped on the carrier, straps and wheels clearly visible, truck moving at constant speed. No people, no text. Photorealistic, cinematic, 5 seconds

### stage-3.mp4 — Таможня и ЭПТС (ТЕРМИНАЛ)

> The same gray Mansory-tuned Audi RS7 Sportback from the reference image, forged carbon aero kit, black wheels with orange ring, orange brake calipers. Scene: overcast morning at a customs terminal, the car stands in an inspection lane in front of a lowered barrier, container stacks and a small customs office building in the background, wet concrete, soft gray daylight. A customs inspector in a high-visibility vest with documents walks past in the background at a distance. Camera: slow cinematic orbit around the front of the car, barrier and terminal visible behind it. No people close to the camera, no readable text. Photorealistic, documentary style, 5 seconds

### stage-4.mp4 — Выдача ключей (ПРИВОЗ НА КОТТЕДЖ)

> The same gray Mansory-tuned Audi RS7 Sportback from the reference image, forged carbon aero kit, black wheels with orange ring. Scene: evening in front of a modern cottage house with warm light in the windows. A car carrier truck is parked on the driveway with its ramp down, and the gray sports car is carefully rolling down the carrier ramp towards the house, driven slowly and precisely. The happy owner waits at the porch in the background, watching. Camera: slow dolly-in from the side of the house, car in the center of the frame, warm cinematic color grade, cozy evening atmosphere. No readable text. Photorealistic, 5 seconds

## Техтребования

- Без звука (сайт всё равно крутит muted).
- После скачивания, если файл тяжелее ~5 МБ — сжать:
  `ffmpeg -i in.mp4 -an -c:v libx264 -crf 26 -movflags +faststart stage-N.mp4`
  (можно отдать мне — сожму сам).
- Имена файлов строго `stage-0.mp4` … `stage-4.mp4`, иначе сайт не подхватит.
