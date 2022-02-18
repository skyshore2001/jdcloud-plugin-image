# jdimage - 图像合成等处理

合成图像模板和参数数据，生成最终的图片。

	图像模板 + 参数数据 = 实例图片

## 安装

	./tool/jdcloud-plugin.sh add ../jdcloud-plugin-jdimage

安装image magick软件版本7以上。

插件通过直接调用image magick软件命令行完成图片处理。

## 图像模板

图像模板示例见：ref/jdimage-tpl-card，测试时可复制到upload/jdimage目录下，改名为card(这样模板名就叫card)。

图像模板是一个目录，JSON文件，属性定义如下：

	{
		list: [
			{
				type: "image",
				value: "background.jpg"
			},
			{
				type: "text",
				pos: "200,50",
				name: "名字", // 参数名
				value: "张三", // 参数默认值，可以不指定
				fill: "#777777", // 颜色
				font: "FZLTHJW.ttf", // 字体
				pointsize: 36, // 字号
			},
			{
				type: "text",
				pos: "200,100",
				name: "职位",
				value: "销售总监",
				fill: "#770000", // 颜色
				font: "FZLTHJW.ttf", // 字体
				pointsize: 24, // 字号
			},
			{
				type: "image",
				pos: "200,150",
				name: "logo",
				value: "logo.png",
			}
		]
	}

说明：

- type: Enum(image-图，text-文)
- name: 可选，如果指定，则表示它是个可变参数，name就是参数名，其值(value)可以被参数数据覆盖。
- value: 可选，在合成时，可被参数数据覆盖。如果value未指定，且参数中也未指定，则不处理该项。
	如果type=image，表示文件路径，一般用目录内的相对路私。
	如果type=text，则是文本内容。

示例参数数据：

	{
		"名字": "李四",
		"职位": "销售总监",
		"logo": "logo.png"
	}

处理命令参考：

	magick -gravity northwest \
		background.jpg \
		-font "FZLTHJW.ttf" -fill '#777' -pointsize 36 -draw 'text 200,50 "李四"'  \
		-font "FZLTHJW.ttf" -fill '#770000' -pointsize 24 -draw 'text 200,100 "销售总监"'  \
		logo.png  -geometry +200+150 -composite \
		out.jpg 

## 接口

根据模板合成图片：

	JDImage.compose()(template, ...) -> {path}

- template: 指定图像模板，对应一个后端目录。如"card"对应`upload/jdimage/card`目录。
	模板事先制作好放置于目录中。

其它参数为图像模板中定义的参数数据。

生成图像到目录: upload/jdimage/out

返回：

- path: 文件相对路径。

示例：

	callSvr("JDImage.compose", {
		template: "card", // 对应模板目录 upload/jdimage/card
		"名字": "李四",
		"职位": "销售总监",
	})

返回示例：

	{
		path: "upload/202202/333.jpg"
	}

通过拼接baseUrl可得到图片完整URL。

## 后端内部接口

	AC0_JDImage.compose(param)

示例：

	$x = new AC0_JDImage();
	$out = $x->compose({
		template: "card",
		"名字": "李四",
		"职位": "销售总监",
	});

