from reportlab.pdfgen import canvas
from reportlab.platypus import SimpleDocTemplate, Paragraph, Table, TableStyle, Spacer
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.pagesizes import A4, landscape
pdfmetrics.registerFont(TTFont('Arial','C:/Windows/Fonts/arial.ttf'))
pdfmetrics.registerFont(TTFont('ArialBold','C:/Windows/Fonts/arialbd.ttf'))
rows = [
('Ввод промокода на сайте','Есть поле ввода, серверное применение и сообщения об ошибках.','Добавить информацию о подарке и невыполненных условиях акции.'),
('Срок действия и активность','Проверяются на сервере.','Распространить существующие проверки на подарочные акции.'),
('Общий лимит и лимит на клиента','Есть настройки, проверки и учёт использований.','Унифицировать проверки сайта и админки; защитить лимиты при одновременном оформлении заказов.'),
('Проверка состава корзины','Есть отбор по товарам, категориям, характеристикам и исключения из акций.','Добавить условия подарка: сумма, количество и необходимые сочетания товаров.'),
('Применение скидки','Реализованы процентная и фиксированная скидки.','Добавить варианты «подарок» и «скидка + подарок».'),
('Автоматическое добавление подарка','В проверенном механизме промокодов не найдено.','Добавлять товар из каталога с заданным количеством и нулевой ценой, сохраняя связь с акцией.'),
('Информация клиенту об условиях','Есть сообщения о недействительном промокоде и отсутствии скидки для товаров.','Показывать подарок, условия получения и подсказку: «Добавьте товаров ещё на 200 грн».'),
('Пересчёт при изменении заказа','Предусмотрен серверный пересчёт скидок.','Повторно проверять право на подарок; удалять его при потере условий и сообщать клиенту.'),
('Оформление оператором','В обычной форме есть применение к сохранённому заказу. В колл-центре найден выбор акций.','Добавить ввод промокода при создании заказа и в колл-центре, автоматическую выдачу подарка и описание условий.'),
('Сохранение в заказе','Сохраняются код, скидка и запись использования.','Сохранять подарочные позиции и условия выдачи на момент оформления.'),
('Статистика использований','Отображаются количество применений, остаток лимита и сроки.','Добавить список заказов, суммы предоставленных скидок и перечень выданных подарков.'),
('Успешные заказы, отмены и возвраты','Есть методы регистрации и снятия использования.','Определить успешный статус; автоматизировать учёт отмен и возвратов, настроить восстановление лимита.'),
('Создание и редактирование акций','Есть создание, редактирование, включение и выключение, сроки, лимиты и область действия.','Добавить настройки подарков и новых условий без изменения кода.'),
('Копирование и архивирование','Отдельные действия копирования и архивирования не найдены; используется мягкое удаление.','Добавить копирование настроек и понятный архив завершённых акций.'),
('Совместимость с другими программами','Есть расчёт дополнительной выгоды купона сверх выбранной акции; бонусы участвуют в оформлении.','Определить и проверить совместимость подарков с именинной скидкой, бонусами и другими акциями.'),
('Проверка возможностей CS-Cart','Проверенный сайт реализован на Laravel / Filament и frontend-3piroga.','Если требуется аудит старого сайта на CS-Cart, отдельно проверить его версию, настройки и модули.'),
]
style=ParagraphStyle('cell',fontName='Arial',fontSize=9,leading=12,textColor=colors.HexColor('#253448'))
head=ParagraphStyle('head',parent=style,fontName='ArialBold',textColor=colors.white)
title=ParagraphStyle('title',fontName='ArialBold',fontSize=20,leading=24,textColor=colors.HexColor('#15364B'))
sub=ParagraphStyle('sub',parent=style,fontSize=9,leading=13,textColor=colors.HexColor('#5D6B78'))
out='D:/laragon/www/myadmin/output/pdf/promo-codes-requirements-comparison.pdf'
doc=SimpleDocTemplate(out,pagesize=landscape(A4),rightMargin=30,leftMargin=30,topMargin=27,bottomMargin=30)
data=[[Paragraph(x,head) for x in ['Требование ТЗ','Что есть сейчас','Что осталось']]]
data += [[Paragraph(x,style) for x in row] for row in rows]
t=Table(data,colWidths=[177,263,342],repeatRows=1,hAlign='LEFT')
t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),colors.HexColor('#15364B')),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),10),('RIGHTPADDING',(0,0),(-1,-1),10),('TOPPADDING',(0,0),(-1,-1),8),('BOTTOMPADDING',(0,0),(-1,-1),8),('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.HexColor('#F0F4F7'),colors.white]),('LINEBELOW',(0,1),(-1,-1),0.35,colors.HexColor('#D9E1E7'))]))
def footer(c,d):
 c.setFont('Arial',8); c.setFillColor(colors.HexColor('#657482')); c.drawString(30,17,'Три Пирога | Сравнение по исходному коду | 24.10.2026'); c.drawRightString(811,17,str(d.page))
doc.build([Paragraph('Промокоды и подарочные акции',title),Spacer(1,5),Paragraph('Три Пирога · Сопоставление предоставленных требований с текущей реализацией',sub),Spacer(1,7),Paragraph('Основание: анализ исходного кода общей админки и frontend-3piroga. Работа на действующем сайте и данные БД не проверялись. Оценка часов и стоимости в документ не включена.',sub),Spacer(1,13),t],onFirstPage=footer,onLaterPages=footer)
from pypdf import PdfReader
r=PdfReader(out)
print('Pages:',len(r.pages))
for i,p in enumerate(r.pages): print(i+1,len(p.extract_text()))
