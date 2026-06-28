import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
  smartcommands: 'src/main.js',
}, {
  assetsPrefix: '',
  thirdPartyLicense: false,
  emptyOutputDirectory: false,
})
