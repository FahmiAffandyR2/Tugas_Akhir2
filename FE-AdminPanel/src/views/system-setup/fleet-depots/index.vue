<template>
  <div>
    <v-row>
      <v-col cols="12" lg="5">
        <v-card class="depot-card">
          <v-card-title><v-icon color="primary" class="mr-2">mdi-garage-variant</v-icon>Depo Armada<v-spacer/><v-btn color="primary" @click="openCreate"><v-icon left>mdi-plus</v-icon>Tambah</v-btn></v-card-title>
          <v-card-subtitle>Kelola lokasi asal dan persebaran armada bus.</v-card-subtitle>
          <v-card-text>
            <v-text-field v-model="search" outlined dense hide-details prepend-inner-icon="mdi-magnify" label="Cari depo atau kota" class="mb-4" />
            <v-skeleton-loader v-if="loading" type="list-item-avatar-three-line@3" />
            <v-list v-else-if="filteredDepots.length" two-line>
              <v-list-item v-for="depot in filteredDepots" :key="depot.id" @click="selectDepot(depot)">
                <v-list-item-avatar color="purple lighten-5"><v-icon color="primary">mdi-bus-multiple</v-icon></v-list-item-avatar>
                <v-list-item-content><v-list-item-title class="font-weight-bold">{{ depot.name }}</v-list-item-title><v-list-item-subtitle>{{ depot.city }} · {{ depot.buses_count }} bus</v-list-item-subtitle></v-list-item-content>
                <v-chip x-small :color="depot.is_active?'success':'grey'" dark>{{ depot.is_active?'Aktif':'Nonaktif' }}</v-chip>
                <v-btn icon small @click.stop="openEdit(depot)"><v-icon small>mdi-pencil</v-icon></v-btn>
                <v-btn icon small color="error" @click.stop="removeDepot(depot)"><v-icon small>mdi-delete</v-icon></v-btn>
              </v-list-item>
            </v-list>
            <v-alert v-else type="info" text>Belum ada depo armada.</v-alert>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" lg="7">
        <v-card class="depot-card overflow-hidden">
          <v-card-title>Persebaran Armada</v-card-title>
          <leaflet-map-loader :center="mapCenter" :zoom="5" :markers="markers" :selected="selectedMarker" class="depot-map" />
        </v-card>
      </v-col>
    </v-row>

    <v-dialog v-model="dialog" max-width="720" persistent>
      <v-card><v-card-title>{{ form.id?'Edit':'Tambah' }} Depo Armada<v-spacer/><v-btn icon @click="dialog=false"><v-icon>mdi-close</v-icon></v-btn></v-card-title>
        <v-card-text><v-form ref="form" v-model="valid"><v-row>
          <v-col cols="12" sm="6"><v-text-field v-model.trim="form.name" outlined label="Nama depo" :rules="required" /></v-col>
          <v-col cols="12" sm="6"><v-text-field v-model.trim="form.city" outlined label="Kota" :rules="required" /></v-col>
          <v-col cols="12"><v-textarea v-model.trim="form.address" outlined rows="2" label="Alamat lengkap" /></v-col>
          <v-col cols="12"><div class="caption mb-2">Klik peta untuk menentukan koordinat depo.</div><leaflet-map-loader :center="formCenter" :zoom="12" :markers="formMarkers" :enabled="true" class="picker-map" @map-click="setCoordinates" /></v-col>
          <v-col cols="6"><v-text-field v-model.number="form.latitude" type="number" step="any" outlined label="Latitude" :rules="coordinateRules" /></v-col>
          <v-col cols="6"><v-text-field v-model.number="form.longitude" type="number" step="any" outlined label="Longitude" :rules="coordinateRules" /></v-col>
          <v-col cols="12" sm="6"><v-text-field v-model.trim="form.contact_name" outlined label="Penanggung jawab" /></v-col>
          <v-col cols="12" sm="6"><v-text-field v-model.trim="form.contact_phone" outlined label="Nomor telepon" /></v-col>
          <v-col cols="12"><v-switch v-model="form.is_active" label="Depo aktif dan dapat digunakan" /></v-col>
        </v-row></v-form></v-card-text>
        <v-card-actions class="pa-5 pt-0"><v-spacer/><v-btn text @click="dialog=false">Batal</v-btn><v-btn color="primary" :loading="saving" @click="save">Simpan</v-btn></v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import LeafletMapLoader from '@/components/LeafletMapLoader.vue'
const emptyForm=()=>({id:null,name:'',city:'',address:'',latitude:-6.208763,longitude:106.845599,contact_name:'',contact_phone:'',is_active:true})
export default{
 components:{LeafletMapLoader},
 data:()=>({depots:[],loading:false,saving:false,search:'',dialog:false,valid:true,selectedMarker:null,form:emptyForm(),required:[v=>!!v||'Wajib diisi'],coordinateRules:[v=>v!==null&&v!==''||'Koordinat wajib diisi']}),
 computed:{
  filteredDepots(){const q=this.search.toLowerCase();return this.depots.filter(d=>`${d.name} ${d.city}`.toLowerCase().includes(q))},
  mapCenter(){return{lat:-2.5,lng:117}},
  markers(){return this.depots.map(d=>({place_id:`depot-${d.id}`,position:{lat:Number(d.latitude),lng:Number(d.longitude)},infoText:`<strong>${d.name}</strong><br>${d.city}<br>${d.buses_count} armada bus`}))},
  formCenter(){return{lat:Number(this.form.latitude)||-6.208763,lng:Number(this.form.longitude)||106.845599}},
  formMarkers(){return[{place_id:'form-depot',position:this.formCenter,infoText:this.form.name||'Lokasi depo'}]},
 },
 created(){this.loadDepots()},
 methods:{
  async loadDepots(){this.loading=true;try{const r=await axios.get('/fleet-depots');this.depots=r.data.depots||[]}catch(e){this.notifyError(e,'Data depo tidak dapat dimuat.')}finally{this.loading=false}},
  openCreate(){this.form=emptyForm();this.dialog=true},
  openEdit(depot){this.form={...depot,latitude:Number(depot.latitude),longitude:Number(depot.longitude)};this.dialog=true},
  selectDepot(depot){this.selectedMarker=`depot-${depot.id}`},
  setCoordinates(place){this.form.latitude=Number(place.geometry.location.lat().toFixed(7));this.form.longitude=Number(place.geometry.location.lng().toFixed(7))},
  async save(){if(!this.$refs.form.validate())return;this.saving=true;try{const r=this.form.id?await axios.put(`/fleet-depots/${this.form.id}`,this.form):await axios.post('/fleet-depots',this.form);this.$notify({type:'success',title:'Berhasil',text:r.data.message});this.dialog=false;await this.loadDepots()}catch(e){this.notifyError(e,'Depo gagal disimpan.')}finally{this.saving=false}},
  async removeDepot(depot){const result=await this.$swal.fire({title:'Hapus depo?',text:`Depo ${depot.name} akan dihapus.`,icon:'warning',showCancelButton:true,confirmButtonText:'Hapus',cancelButtonText:'Batal'});if(!result.isConfirmed)return;try{const r=await axios.delete(`/fleet-depots/${depot.id}`);this.$notify({type:'success',title:'Berhasil',text:r.data.message});await this.loadDepots()}catch(e){this.notifyError(e,'Depo gagal dihapus.')}},
  notifyError(error,fallback){const data=error.response&&error.response.data;const errors=data&&data.errors;this.$notify({type:'error',title:'Gagal',text:errors?Object.values(errors).flat().join(' '):(data&&data.message)||fallback})},
 }
}
</script>
<style scoped>.depot-card{position:relative;z-index:0;isolation:isolate;border-radius:14px!important}.depot-map,.picker-map{position:relative;z-index:0;overflow:hidden}.depot-map ::v-deep .leaflet-map{height:610px}.picker-map ::v-deep .leaflet-map{height:280px}@media(max-width:1264px){.depot-map ::v-deep .leaflet-map{height:430px}}</style>
