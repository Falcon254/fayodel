const csvProducts = [
    // KIDS BIKES - Size 12"
    { id: 1, name: "Kids Bike 12\" - Red", price: 4500, category: 'kids', stock: 'In Stock', badge: '12"', description: 'Size: 12" Kids bicycle', image: 'kid.jpg' },
    { id: 2, name: "Kids Bike 12\" - Blue", price: 4500, category: 'kids', stock: 'In Stock', badge: '12"', description: 'Size: 12" Kids bicycle', image: 'blue.jpg' },
    { id: 3, name: "Kids Bike 12\" - Green", price: 4200, category: 'kids', stock: 'Limited Stock', badge: '12"', description: 'Size: 12" Kids bicycle', image: 'kid.jpg' },
    
    // KIDS BIKES - Size 16"
    { id: 4, name: "Kids Bike 16\" - Black", price: 5200, category: 'kids', stock: 'In Stock', badge: '16"', description: 'Size: 16" Kids bicycle', image: 'size16.jpg' },
    { id: 5, name: "Kids Bike 16\" - Red", price: 5200, category: 'kids', stock: 'In Stock', badge: '16"', description: 'Size: 16" Kids bicycle', image: 'size16.jpg' },
    { id: 6, name: "Kids Bike 16\" - Purple", price: 5500, category: 'kids', stock: 'In Stock', badge: '16"', description: 'Size: 16" Kids bicycle', image: 'kid.jpg' },
    
    // KIDS BIKES - Size 18"
    { id: 7, name: "Kids Bike 18\" - Yellow", price: 6200, category: 'kids', stock: 'In Stock', badge: '18"', description: 'Size: 18" Kids bicycle', image: '18.jpg' },
    { id: 8, name: "Kids Bike 18\" - Orange", price: 6200, category: 'kids', stock: 'Limited Stock', badge: '18"', description: 'Size: 18" Kids bicycle', image: '182.jpg' },
    { id: 9, name: "Kids Bike 18\" - White", price: 6500, category: 'kids', stock: 'In Stock', badge: '18"', description: 'Size: 18" Kids bicycle', image: '18blue.jpg' },
    
    // KIDS BIKES - Size 24"
    { id: 10, name: "Kids Bike 24\" - Teal", price: 8500, category: 'kids', stock: 'In Stock', badge: '24"', description: 'Size: 24" Kids bicycle', image: 'kid.jpg' },
    { id: 11, name: "Kids Bike 24\" - Silver", price: 8500, category: 'kids', stock: 'In Stock', badge: '24"', description: 'Size: 24" Kids bicycle', image: 'blue.jpg' },
    
    // TOYS & RIDE-ONS
    { id: 12, name: "Tricycle - Kids Ride-On", price: 3500, category: 'kids', stock: 'In Stock', badge: '-20%', description: 'type:tricycle Kids tricycle for ages 2-5', image: 'kid.jpg' },
    { id: 13, name: "Balance Bike - Wooden", price: 2800, category: 'kids', stock: 'In Stock', badge: '-10%', description: 'type:balance Balance bike for toddlers', image: 'kid.jpg' },
    
    // ADULT BIKES - Size 20"
    { id: 14, name: "BMX Bike 20\" - Chrome", price: 9500, category: 'bikes', stock: 'In Stock', badge: '20"', description: 'Size: 20" type:bmx BMX bike for tricks', image: 'bike.jpeg' },
    { id: 15, name: "BMX Bike 20\" - Gold", price: 10000, category: 'bikes', stock: 'Limited Stock', badge: '20"', description: 'Size: 20" type:bmx BMX bike pro', image: 'bike.jpeg' },
    
    // ADULT BIKES - Size 26"
    { id: 16, name: "Mountain Bike 26\" - Black", price: 18500, category: 'bikes', stock: 'In Stock', badge: '26"', description: 'Size: 26" type:mountain Mountain bike MTB professional', image: 'mountain.jpg' },
    { id: 17, name: "Road Bike 26\" - Red", price: 15000, category: 'bikes', stock: 'In Stock', badge: '26"', description: 'Size: 26" type:road On road racing bike', image: 'bike.jpeg' },
    { id: 18, name: "Fat Bike 26\" - Silver", price: 22000, category: 'bikes', stock: 'Limited Stock', badge: '26"', description: 'Size: 26" type:fat Fat bike with wide tires', image: 'blades.jpg' },
    
    // ADULT BIKES - Size 27.5"
    { id: 19, name: "Mountain Bike 27.5\" - Blue", price: 19500, category: 'bikes', stock: 'In Stock', badge: '27.5"', description: 'Size: 27.5" type:mountain Mountain bike with suspension', image: 'mountain.jpg' },
    { id: 20, name: "Foldable Bike 27.5\" - Green", price: 16000, category: 'bikes', stock: 'In Stock', badge: '27.5"', description: 'Size: 27.5" type:fold Foldable portable bike', image: 'bike.jpeg' },
    
    // ADULT BIKES - Size 29"
    { id: 21, name: "Mountain Bike 29\" - Teal", price: 21000, category: 'bikes', stock: 'In Stock', badge: '29"', description: 'Size: 29" type:mountain Large wheel mountain bike', image: 'mountain.jpg' },
    
    // ROAD BIKES - 700c
    { id: 22, name: "Road Bike 700c - Carbon", price: 28500, category: 'bikes', stock: 'Limited Stock', badge: '700c', description: 'Size: 700c type:road Carbon frame road bike', image: 'bike.jpeg' },
    { id: 23, name: "Road Bike 700c - Aluminum", price: 18000, category: 'bikes', stock: 'In Stock', badge: '700c', description: 'Size: 700c type:road Aluminum road bike', image: 'bike.jpeg' },
    
    // ACCESSORIES
    { id: 24, name: "Safety Helmet - Adults", price: 3500, category: 'accessories', stock: 'In Stock', badge: '-15%', description: 'type:helmet Professional safety helmet', image: 'bike.jpeg' },
    { id: 25, name: "LED Bike Light - Front", price: 1200, category: 'accessories', stock: 'In Stock', badge: '-10%', description: 'type:light Front LED bike light', image: 'bike.jpeg' },
    { id: 26, name: "Pump - Manual", price: 800, category: 'accessories', stock: 'In Stock', badge: '-5%', description: 'type:pump Manual air pump for bikes', image: 'bike.jpeg' },
    { id: 27, name: "Chain Lock - Heavy Duty", price: 2500, category: 'accessories', stock: 'Limited Stock', badge: '-20%', description: 'type:lock Heavy duty chain lock', image: 'bike.jpeg' },
    
    // E-MOBILITY & SCOOTERS
    { id: 28, name: "Electric Scooter - Pro", price: 45000, category: 'scooter', stock: 'In Stock', badge: '-25%', description: 'type:scooter Electric scooter 40km range', image: 'scooter.jpg' },
    { id: 29, name: "Electric Bike - Mountain", price: 75000, category: 'emobility', stock: 'In Stock', badge: '-30%', description: 'type:ebike type:electric Electric mountain bike with battery', image: 'bike.jpeg' },
    { id: 30, name: "Electric Scooter - Urban", price: 35000, category: 'scooter', stock: 'Limited Stock', badge: '-15%', description: 'type:scooter Urban electric scooter', image: 'scooter.jpg' },
    
    // FURNITURE
    { id: 31, name: "Modern Sofa - Grey", price: 65000, category: 'furniture', stock: 'In Stock', badge: '-20%', description: 'type:sofa Grey modern 3-seater sofa', image: 'creamy.jpg' },
    { id: 32, name: "Wooden Study Desk", price: 15000, category: 'furniture', stock: 'In Stock', badge: '-10%', description: 'type:table Wooden study desk with drawers', image: 'studydesk.jpeg' },
    { id: 33, name: "Queen Bed Frame - Oak", price: 45000, category: 'furniture', stock: 'Limited Stock', badge: '-15%', description: 'type:bed Oak wood queen bed frame', image: 'bike.jpeg' },
    { id: 34, name: "Office Chair - Ergonomic", price: 12000, category: 'furniture', stock: 'In Stock', badge: '-5%', description: 'type:chair Ergonomic office chair', image: 'bike.jpeg' },
    
    // GENERATORS
    { id: 35, name: "Petrol Generator 2.5kVA", price: 35000, category: 'generator', stock: 'In Stock', badge: '-15%', description: 'type:petrol 2.5kVA portable petrol generator', image: 'bike.jpeg' },
    { id: 36, name: "Solar Generator - 5kW", price: 125000, category: 'generator', stock: 'Limited Stock', badge: '-20%', description: 'type:solar Solar panel generator 5000W', image: 'bike.jpeg' },
    { id: 37, name: "Diesel Generator 7.5kVA", price: 85000, category: 'generator', stock: 'In Stock', badge: '-10%', description: 'type:diesel Industrial diesel generator 7500W', image: 'bike.jpeg' },
];
