# jdimage - 图像合成等处理

合成图像模板和参数数据，生成最终的图片。

	图像模板 + 参数数据 = 实例图片

## 安装

	./tool/jdcloud-plugin.sh add ../jdcloud-plugin-jdimage

### 安装ImageMagick软件

由于插件通过image magick软件命令行完成图片处理，须安装image magick软件。

在Windows平台，请安装版本7以上(安装文件如ImageMagick-7.0.8-12-Q16-x64-dll.exe)。注意：版本7以前命令名为convert，版本7之后名为magick.
然后确保以下命令可用：

	sh
	magick

注意：Win10等系统中apache服务可能无法执行外部命令，造成接口或调用卡死（在进程管理器中可用sh进程），通过在Windows服务管理器中将服务的登录用户（默认是SYSTEM）改为当前用户可以解决。

在CentOS7上，安装ImageMagick:

	sudo yum install ImageMagick

由于默认是6.9版本（命令名为convert），须做一下兼容，让magick命令可用

	sudo ln -sf /usr/bin/convert /usr/bin/magick

## 图像模板

插件自带一个示例模板，目录为 upload/jdimage/card

图像模板是一个目录，其中 index.json 文件定义了模板，示例如下：

	[
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
					font: "FZLTHJW.TTF", // 字体
					size: 36, // 字号
				},
				{
					type: "text",
					pos: "200,100",
					name: "职位",
					value: "销售总监",
					fill: "#770000", // 颜色
					font: "FZLTHJW.TTF", // 字体
					size: 24, // 字号
				},
				{
					type: "image",
					pos: "200,150",
					size: "80,80", // 图片的宽和高
					name: "logo",
					value: "logo.png",
				}
			]
		}
	]

**注意**：其中引用的文件名，必须大小写与文件一致，否则Linux平台下找不到文件!

模板是一个数组，支持多页，每一项是一页。

说明：

- type: Enum(image-图，text-文)
- name: 可选，如果指定，则表示它是个可变参数，name就是参数名，其值(value)可以被参数数据覆盖。
- value: 可选，在合成时，可被参数数据覆盖。如果value未指定，且参数中也未指定，则不处理该项。
	如果type=image，表示文件路径，一般用目录内的相对路私。
	如果type=text，则是文本内容。
- size: 用于type=text时，表示字号; 用于type=image时, 是可选的，指定图片长宽。

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
		logo.png  -geometry 80x80+200+150 -composite \
		out.jpg 

如果多页，则生成多个命令。

多页模板示例：

	[
		{
			name: "正面",
			list: {
				...
			}
		},
		{
			name: "反面",
			list: {
				...
			}
		}
	]

如果只有一页，生成图片名为xx.jpg，如果是多页，图片名后会带上后缀 xx-1.jpg, xx-2.jpg 这样。

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

	[
		{ path: "upload/202202/333.jpg" }
	]

如果有多张图，会加数字后缀：

	[
		{ path: "upload/202202/333-1.jpg" }
		{ path: "upload/202202/333-2.jpg" }
	]

通过拼接baseUrl可得到图片完整URL。

## 后端内部接口

	AC0_JDImage.compose(param)

示例：

	$img = new AC0_JDImage();
	$rv = $img->compose({
		template: "card",
		"名字": "李四",
		"职位": "销售总监",
	});
	// rv是个数组: [ { path: "xxx.jpg" } ]

